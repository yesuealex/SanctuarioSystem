
(() => {
    "use strict";

    const $ = id => document.getElementById(id);
    const frame = $("preview");

    if (!frame) return;

    const message = $("message");
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
    const IMAGE_TYPES = [
        "image/jpeg",
        "image/png",
        "image/webp",
        "image/gif"
    ];

    let doc = null;
    let ready = false;
    let busy = false;
    let previewMode = false;
    let savedHTML = "";
    let hasDraft = !!window.CMS_HAS_DRAFT;

    let history = [];
    let historyIndex = -1;

    let selectedHost = null;
    let activeFields = [];
    let slideFields = [];

    let editPage = 0;
    let slidePage = 0;

    let observer = null;
    let installedDoc = null;
    let refreshTimer = null;
    let visibilityFrame = 0;

    const imageURLs = new Set();

    const EDIT_SELECTOR =
        ".cms-container-edit[data-cms-editor]";

    const PENCIL = `
        <svg width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round">
            <path d="M12 20h9"/>
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L9 17l-4 1 1-4Z"/>
        </svg>
    `;

    const UPLOAD = `
        <svg width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round">
            <path d="M12 16V3"/>
            <path d="m7 8 5-5 5 5"/>
            <path d="M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>
        </svg>
    `;

    // ==========================================
    // GENERAL
    // ==========================================

    function notify(text, error = false) {
        if (!message) return;

        message.textContent = text;
        message.style.color = error
            ? "#a83232"
            : "#315b36";
    }

    function normalizeText(value) {
        return String(value || "")
            .replace(/\u00a0/g, " ")
            .replace(/\r\n?/g, "\n")
            .split("\n")
            .map(line => line.trim())
            .filter(Boolean)
            .join(" ")
            .replace(/[ \t]{2,}/g, " ")
            .trim();
    }

    function escapeHTML(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");
    }

    function cleanHTML() {
        if (!doc?.documentElement) return "";

        const clone = doc.documentElement.cloneNode(true);

        clone.classList.remove("cms-editor-preview");

        clone.querySelectorAll("[data-cms-editor]")
            .forEach(element => element.remove());

        clone.querySelectorAll(
            "[data-cms-editable-host]"
        ).forEach(element => {
            element.removeAttribute("data-cms-editable-host");
        });

        clone.querySelectorAll(
            "[data-cms-original-position]"
        ).forEach(element => {
            const original = element.getAttribute(
                "data-cms-original-position"
            );

            if (original === "__none__") {
                element.style.removeProperty("position");
            } else {
                element.style.position = original;
            }

            element.removeAttribute(
                "data-cms-original-position"
            );
        });

        return "<!DOCTYPE html>\n" + clone.outerHTML;
    }

    function updateButtons() {
        const disabled = !ready || busy || previewMode;

        ["saveDraft", "publishAll", "addSection"].forEach(id => {
            if ($(id)) $(id).disabled = disabled;
        });

        if ($("undo")) {
            $("undo").disabled =
                disabled || historyIndex < 0;
        }

        if ($("redo")) {
            $("redo").disabled =
                disabled ||
                historyIndex >= history.length - 1;
        }

        if ($("previewMode")) {
            $("previewMode").disabled = !ready || busy;
        }

        if ($("draftStatus")) {
            $("draftStatus").textContent =
                cleanHTML() !== savedHTML
                    ? "Unsaved changes"
                    : hasDraft
                        ? "Draft saved"
                        : "Published";
        }
    }

    // ==========================================
    // UNDO / REDO
    // ==========================================

    function recordChanges(changes) {
        const effective = changes.filter(
            change => change.before !== change.after
        );

        if (!effective.length) {
            updateButtons();
            return;
        }

        history = history.slice(0, historyIndex + 1);
        history.push(effective);
        historyIndex = history.length - 1;

        updateButtons();
    }

    function applyChanges(changes, forward = true) {
        if (!ready || !doc?.body) return false;

        for (const change of changes) {
            if (!change.element?.isConnected) {
                notify(
                    "Cannot restore this edit. Refresh the editor.",
                    true
                );
                return false;
            }
        }

        for (const change of changes) {
            const value = forward
                ? change.after
                : change.before;

            if (change.kind === "faqQuestion") {
                const textNode = [...change.element.childNodes]
                    .find(node => node.nodeType === 3);

                if (textNode) {
                    textNode.textContent = value;
                } else {
                    change.element.insertBefore(
                        doc.createTextNode(value),
                        change.element.firstChild
                    );
                }

            } else if (change.kind === "partnerDetail") {
                const textNode = [...change.element.childNodes]
                    .filter(node => node.nodeType === 3)
                    .pop();

                if (textNode) {
                    textNode.textContent = value;
                } else {
                    change.element.appendChild(
                        doc.createTextNode(value)
                    );
                }

            } else if (change.kind === "text") {
                change.element.textContent = value;

            } else if (change.kind === "html") {
                change.element.innerHTML = value;

            } else if (change.kind === "src") {
                if (value === null || value === "") {
                    change.element.removeAttribute("src");
                } else {
                    change.element.setAttribute("src", value);
                }

            } else if (change.kind === "href") {
                change.element.setAttribute("href", value);

            } else if (change.kind === "background") {
                change.element.style.backgroundImage = value;
            }
        }

        updateButtons();
        queueVisibilityCheck();

        return true;
    }

    // ==========================================
    // EDIT BUTTON STYLES
    // ==========================================

    function installEditorStyles() {
        if (doc.getElementById("cms-editor-styles")) {
            return;
        }

        const style = doc.createElement("style");

        style.id = "cms-editor-styles";
        style.setAttribute("data-cms-editor", "true");

        style.textContent = `
            .cms-container-edit {
                position: absolute !important;
                top: 12px !important;
                right: 12px !important;
                bottom: auto !important;
                left: auto !important;
                transform: none !important;

                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 7px !important;

                width: 88px !important;
                height: 34px !important;
                min-width: 88px !important;
                min-height: 34px !important;

                padding: 0 10px !important;
                margin: 0 !important;

                background: #315b36 !important;
                color: #ffffff !important;

                border: 1px solid #d5e4d1 !important;
                border-radius: 9px !important;

                box-shadow:
                    0 3px 12px rgba(12,39,17,.18) !important;

                font: 600 12px Poppins,Arial,sans-serif !important;
                line-height: 1 !important;
                letter-spacing: 0 !important;
                text-transform: none !important;
                white-space: nowrap !important;

                cursor: pointer !important;
                pointer-events: auto !important;
                z-index: 100 !important;

                opacity: 0 !important;
                visibility: hidden !important;

                transition:
                    opacity .15s ease,
                    background-color .15s ease !important;

                animation: none !important;
            }

            .cms-container-edit svg {
                display: block !important;
                width: 15px !important;
                height: 15px !important;
                flex-shrink: 0 !important;
                pointer-events: none !important;
            }

            .cms-container-edit span {
                color: inherit !important;
                font: inherit !important;
                pointer-events: none !important;
            }

            
/* Show Edit only when hovering its own container */
[data-cms-editable-host]:hover:not(
    :has([data-cms-editable-host]:hover)
) > .cms-container-edit:not(.cms-edit-obscured),
[data-cms-editable-host]:focus-within:not(
    :has([data-cms-editable-host]:focus-within)
) > .cms-container-edit:not(.cms-edit-obscured) {
    opacity: 1 !important;
    visibility: visible !important;
}


            .map-layout > .locations > .location
            > .cms-container-edit {
                top: 7px !important;
                right: 7px !important;
                width: 62px !important;
                min-width: 62px !important;
                height: 27px !important;
                min-height: 27px !important;
                padding: 0 6px !important;
                font-size: 10px !important;
            }

            .map-layout > .locations > .location
            > strong {
                display: block;
                padding-right: 68px;
            }

            .cms-container-edit:hover {
                background: #23482a !important;
            }

            .cms-container-edit.cms-edit-obscured {
                opacity: 0 !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }

            html.cms-editor-preview .cms-container-edit {
                display: none !important;
            }

            @media (max-width: 600px) {
                .cms-container-edit {
                    top: 10px !important;
                    right: 10px !important;
                    width: 78px !important;
                    min-width: 78px !important;
                    height: 32px !important;
                    min-height: 32px !important;
                    font-size: 11px !important;
                }
            }
                
        
/* ABOUT OUR FARM — EDIT BUTTON POSITIONING */

/* Picture container */
.about > .about-img[data-cms-editable-host] {
    position: relative !important;
}

/* Edit Picture — inside the image, away from corners */
.about > .about-img > .cms-container-edit {
    top: 18px !important;
    right: 18px !important;
    left: auto !important;
    bottom: auto !important;
    transform: none !important;
    z-index: 10 !important;
}

/* Text container */
.about > div:not(.about-img)[data-cms-editable-host] {
    position: relative !important;
}

/* ABOUT OUR FARM — TEXT EDIT BUTTON */

.about > div:not(.about-img)[data-cms-editable-host] {
    position: relative !important;
    padding-top: 42px !important;
}

.about > div:not(.about-img) > .cms-container-edit {
    position: absolute !important;
    top: 4px !important;
    right: 4px !important;
    left: auto !important;
    bottom: auto !important;
    transform: none !important;
    z-index: 10 !important;
}
    


/* GROWING WITH PURPOSE — EDIT BESIDE HEADING */

.section-head > div[data-cms-editable-host] > h2 {
    width: fit-content !important;
    max-width: 100% !important;
    position: relative !important;
}

.section-head > div[data-cms-editable-host] > .cms-container-edit {
    top: 34px !important;
    left: 510px !important;
    right: auto !important;
    bottom: auto !important;
    transform: none !important;
}


        `;

        doc.head.appendChild(style);
    }

    // ==========================================
    // EDITABLE CONTAINERS
    // ==========================================

    function headingText(element) {
        return [...element.querySelectorAll(
            "h1,h2,h3,h4,h5,h6"
        )]
            .map(heading => heading.textContent.trim())
            .join(" ")
            .toLowerCase();
    }

    function isFindSanctuario(element) {
        return /find\s+sanctuario|visit\s+sanctuario/i
            .test(headingText(element));
    }

    function isProductsSection(element) {
        return (
            element.matches(
                "#products,.products,.products-section"
            ) ||
            !!element.querySelector(".product[data-category]")
        );
    }

    function isVisible(element) {
        if (!element?.isConnected) return false;

        const style =
            doc.defaultView.getComputedStyle(element);

        const rect = element.getBoundingClientRect();

        return (
            style.display !== "none" &&
            style.visibility !== "hidden" &&
            rect.width > 0 &&
            rect.height > 0
        );
    }

    
function getEditableHosts() {
    const hosts = [];

    const hero = doc.querySelector(".hero");

    if (hero && hero.querySelector(".slide")) {
        hosts.push(hero);
    }

    const sections = [
        ...doc.querySelectorAll(
            "section,.section,.pagehero"
        )
    ];

    for (const section of sections) {
        if (!isVisible(section)) continue;

        if (section.closest(
            "header,nav,.navbar,footer"
        )) {
            continue;
        }

        if (
            hero &&
            (section === hero || hero.contains(section))
        ) {
            continue;
        }

        // CMS PARTNERSHIP FAQ EDITORS V1
        const engageGrid = section.querySelector(
            ".container.engage-grid"
        );

        if (engageGrid) {
            const partner = engageGrid.querySelector(".partner");
            const faq = engageGrid.querySelector(".faq");

            if (partner && faq) {
                hosts.push(partner);

                partner.querySelectorAll(
                    ".partner-list > div"
                ).forEach(item => hosts.push(item));

                faq.querySelectorAll(
                    ".faq-item"
                ).forEach(item => hosts.push(item));

                continue;
            }
        }

        // CMS CONTACT INFO EDITORS V1
        const contactCard = section.querySelector(
            ".container.contact > .contact-card"
        );

        if (contactCard) {
            const heading = contactCard.querySelector(
                ":scope > .kicker"
            );

            const title = contactCard.querySelector(
                ":scope > h2"
            );

            if (heading && title) {
                const headingHost = doc.createElement("div");
                headingHost.setAttribute(
                    "data-cms-contact-heading",
                    ""
                );

                // Keep the original elements and layout.
                // The host is a logical editor group.
                headingHost.style.display = "contents";
                heading.before(headingHost);
                headingHost.append(heading, title);

                hosts.push(headingHost);
            }

            contactCard.querySelectorAll(
                ":scope > .contact-row"
            ).forEach(row => hosts.push(row));

            continue;
        }

        // CMS MISSION VISION CARDS V1
        const missionVisionCards = [
            ...section.querySelectorAll(
                ".mission-vision > *, .mission-vision-grid > *, .mv-grid > *"
            )
        ].filter(card =>
            /our mission|our vision/i.test(
                headingText(card)
            )
        );

        if (missionVisionCards.length === 2) {
            missionVisionCards.forEach(card => hosts.push(card));
            continue;
        }

        // CMS MISSION VISION ACTUAL CARDS V2
        const missionVision = [
            ...section.querySelectorAll(".two > article.box")
        ];

        if (
            missionVision.length === 2 &&
            /our mission/i.test(headingText(missionVision[0])) &&
            /our vision/i.test(headingText(missionVision[1]))
        ) {
            missionVision.forEach(card => hosts.push(card));
            continue;
        }

        if (isFindSanctuario(section)) continue;

        // CMS: independent farm process editors
        const processContainer = section.querySelector(
            ".container.about"
        );

        if (processContainer) {
            const processCards = [
                ...processContainer.querySelectorAll(
                    ":scope > .locations > .location"
                )
            ];

            const processText = [
                ...processContainer.children
            ].find(child =>
                child.matches("div") &&
                child.querySelector(".kicker, h2") &&
                !child.matches(".locations")
            );

            if (
                processCards.length === 3 &&
                processText &&
                processText.textContent.includes(
                    "Care From Preparation to Harvest"
                )
            ) {
                hosts.push(processText);

                processCards.forEach(card => {
                    hosts.push(card);
                });

                continue;
            }
        }

        const aboutContainer = section.querySelector(
            ".container.about"
        );

        if (aboutContainer) {
            const pictureHost = aboutContainer.querySelector(
                ":scope > .about-img"
            );

            const textHost = [
                ...aboutContainer.children
            ].find(element =>
                element !== pictureHost &&
                element.querySelector(".kicker, h2")
            );

            if (pictureHost && textHost) {
                // Edit Picture: only the farm image.
                hosts.push(pictureHost);

                // Edit Text: heading, description and stats.
                hosts.push(textHost);

                // Prevent the section-wide Edit button.
                continue;
            }
        }

        if (
            isProductsSection(section) &&
            !section.matches(".pagehero, .cover-banner, .hero-banner, .page-banner")
        ) {
            section.querySelectorAll(
                ".product[data-category]"
            ).forEach(product => {
                if (!hosts.includes(product)) {
                    hosts.push(product);
                }
            });

            continue;
        }

        // WHY SANCTUARIO / GROWING WITH PURPOSE
        // Separate the heading and four feature cards.
        const featureCards = [
            ...section.querySelectorAll(
                ".features > .feature"
            )
        ];

        const sectionHeading = section.querySelector(
            ".section-head"
        );

        const isGrowingWithPurpose =
            featureCards.length === 4 &&
            sectionHeading &&
            sectionHeading.textContent.includes(
                "Growing With Purpose"
            );

        if (isGrowingWithPurpose) {
            const headingHost =
                sectionHeading.querySelector(":scope > div");

            if (headingHost) {
                hosts.push(headingHost);
            }

            featureCards.forEach(card => {
                hosts.push(card);
            });

            continue;
        }

        const parentSection =
            section.parentElement?.closest(
                "section,.section,.pagehero"
            );

        if (
            parentSection &&
            !isProductsSection(parentSection)
        ) {
            continue;
        }

        hosts.push(section);
    }

    doc.querySelectorAll(
        ".product[data-category]"
    ).forEach(product => {
        if (
            !hosts.includes(product) &&
            !product.closest("footer")
        ) {
            hosts.push(product);
        }
    });

    doc.querySelectorAll(".map-layout > .locations > .location")
        .forEach(card => {
            if (isVisible(card)) hosts.push(card);
        });

    return [...new Set(hosts)];
}


    function restoreHostPosition(host) {
        if (!host.hasAttribute(
            "data-cms-original-position"
        )) {
            return;
        }

        const original = host.getAttribute(
            "data-cms-original-position"
        );

        if (original === "__none__") {
            host.style.removeProperty("position");
        } else {
            host.style.position = original;
        }

        host.removeAttribute(
            "data-cms-original-position"
        );
    }

    function prepareHost(host) {
        if (host.hasAttribute(
            "data-cms-editable-host"
        )) {
            return;
        }

        host.setAttribute(
            "data-cms-editable-host",
            "true"
        );

        const computed =
            doc.defaultView.getComputedStyle(host);

        if (computed.position === "static") {
            host.setAttribute(
                "data-cms-original-position",
                host.style.position || "__none__"
            );

            host.style.position = "relative";
        }
    }

    function makeEditButton(host) {
        const button = doc.createElement("button");

        button.type = "button";
        button.className = "cms-container-edit";
        button.setAttribute("data-cms-editor", "true");

        const isAboutPicture =
    host.matches(".about > .about-img");

const isAboutText =
    host.matches(".about > div:not(.about-img)");

button.innerHTML = PENCIL + "<span>Edit</span>";

        button.setAttribute(
            "aria-label",
            host.matches(".hero")
                ? "Edit slideshow"
                : "Edit this container"
        );

        button.addEventListener("click", event => {
            event.preventDefault();
            event.stopPropagation();

            if (
                previewMode ||
                busy ||
                !host.isConnected
            ) {
                return;
            }

            if (
                host.matches(".hero") &&
                host.querySelector(".slide")
            ) {
                openSlideshow();
            } else {
                openEdit(host);
            }
        });

        return button;
    }

    function installContainerButtons() {
        if (!ready || !doc?.body) return;

        const hosts = getEditableHosts();
        const valid = new Set(hosts);

        doc.querySelectorAll(
            "[data-cms-editable-host]"
        ).forEach(host => {
            if (valid.has(host)) return;

            [...host.children]
                .filter(child =>
                    child.matches?.(EDIT_SELECTOR)
                )
                .forEach(button => button.remove());

            host.removeAttribute(
                "data-cms-editable-host"
            );

            restoreHostPosition(host);
        });

        hosts.forEach(host => {
            prepareHost(host);

            const existing = [...host.children]
                .find(child =>
                    child.matches?.(EDIT_SELECTOR)
                );

            if (!existing) {
                host.appendChild(makeEditButton(host));
            }
        });

        queueVisibilityCheck();
    }

    // ==========================================
    // NAVBAR OVERLAP PROTECTION
    // ==========================================

    function getNavigationBottom() {
        let bottom = 0;

        doc.querySelectorAll(
            "header,nav,.navbar,.site-header,.main-header"
        ).forEach(element => {
            const style =
                doc.defaultView.getComputedStyle(element);

            if (
                style.display === "none" ||
                style.visibility === "hidden"
            ) {
                return;
            }

            const rect = element.getBoundingClientRect();

            if (
                rect.top <= 1 &&
                rect.bottom > 0
            ) {
                bottom = Math.max(bottom, rect.bottom);
            }
        });

        return bottom;
    }

    // CMS WELCOME HEADING BUTTON POSITION V4
    function positionWelcomeEdit() {
        if (!doc?.body) return;

        const heading = [...doc.querySelectorAll("h1,h2,h3,h4")]
            .find(element =>
                /Welcome to Sanctuario Nature Farms Website/i.test(
                    normalizeText(element.textContent)
                )
            );

        if (!heading) return;

        const host = heading.closest("[data-cms-editable-host]");
        const button = host && [...host.children].find(child =>
            child.matches?.(EDIT_SELECTOR)
        );

        if (!button) return;

        const hostRect = host.getBoundingClientRect();
        const headingRect = heading.getBoundingClientRect();

        const range = doc.createRange();
        range.selectNodeContents(heading);

        const textRects = [...range.getClientRects()].filter(rect =>
            rect.width > 0 && rect.height > 0
        );

        const textRect = textRects.at(-1) || headingRect;

        const buttonWidth = button.offsetWidth || 88;
        const buttonHeight = button.offsetHeight || 34;

        let left = textRect.right - hostRect.left + 12;
        let top = textRect.top - hostRect.top +
            (textRect.height - buttonHeight) / 2;

        const maxLeft = Math.max(
            8,
            hostRect.width - buttonWidth - 8
        );

        left = Math.max(8, Math.min(left, maxLeft));

        top = Math.max(
            4,
            Math.min(
                top,
                Math.max(4, hostRect.height - buttonHeight - 4)
            )
        );

        button.style.setProperty(
            "left",
            `${Math.round(left)}px`,
            "important"
        );

        button.style.setProperty(
            "top",
            `${Math.round(top)}px`,
            "important"
        );

        button.style.setProperty("right", "auto", "important");
        button.style.setProperty("bottom", "auto", "important");
        button.style.setProperty("transform", "none", "important");
    }
    function updateButtonVisibility() {
        visibilityFrame = 0;

        if (!ready || !doc) return;

        positionWelcomeEdit();

        const navBottom = getNavigationBottom();

        const viewportHeight =
            doc.documentElement.clientHeight;

        const viewportWidth =
            doc.documentElement.clientWidth;

        doc.querySelectorAll(EDIT_SELECTOR)
            .forEach(button => {
                const rect =
                    button.getBoundingClientRect();

                const hostRect =
                    button.parentElement.getBoundingClientRect();

                const obscured =
                    previewMode ||
                    busy ||
                    rect.top < navBottom + 12 ||
                    rect.top < 0 ||
                    rect.bottom > viewportHeight ||
                    rect.left < 0 ||
                    rect.right > viewportWidth ||
                    rect.top < hostRect.top - 1 ||
                    rect.bottom > hostRect.bottom + 1;

                button.classList.toggle(
                    "cms-edit-obscured",
                    obscured
                );
            });
    }

    function queueVisibilityCheck() {
        if (visibilityFrame) return;

        visibilityFrame = requestAnimationFrame(
            updateButtonVisibility
        );
    }

    function queueContainerRefresh() {
        if (refreshTimer) return;

        refreshTimer = setTimeout(() => {
            refreshTimer = null;

            if (ready) {
                installContainerButtons();
            }
        }, 100);
    }

    // ==========================================
    // INSTALL EDITOR
    // ==========================================

    function installEditor() {
        doc = frame.contentDocument;

        if (!doc?.body) {
            throw new Error(
                "Website preview unavailable."
            );
        }

        ready = true;

        observer?.disconnect();

        installEditorStyles();
        installContainerButtons();

        if (installedDoc !== doc) {
            installedDoc = doc;

            doc.addEventListener(
                "scroll",
                queueVisibilityCheck,
                {
                    passive: true,
                    capture: true
                }
            );

            doc.defaultView.addEventListener(
                "scroll",
                queueVisibilityCheck,
                { passive: true }
            );

            doc.defaultView.addEventListener(
                "resize",
                queueVisibilityCheck,
                { passive: true }
            );

            doc.addEventListener(
                "click",
                event => {
                    if (event.target.closest?.("a")) {
                        event.preventDefault();
                    }
                },
                true
            );

            doc.addEventListener(
                "submit",
                event => event.preventDefault(),
                true
            );
        }

        observer = new MutationObserver(mutations => {
            const structuralChange =
                mutations.some(mutation =>
                    [
                        ...mutation.addedNodes,
                        ...mutation.removedNodes
                    ].some(node =>
                        node.nodeType === 1 &&
                        !node.matches?.(
                            "[data-cms-editor]"
                        )
                    )
                );

            if (structuralChange) {
                queueContainerRefresh();
            }
        });

        observer.observe(doc.body, {
            childList: true,
            subtree: true
        });

        savedHTML = cleanHTML();

        history = [];
        historyIndex = -1;

        applyPreviewMode();
        updateButtons();
        queueVisibilityCheck();

        sendSlideCommand("pause");
    }

    // ==========================================
    // EDITABLE FIELDS
    // ==========================================

    
function editableFields(host) {
    const fields = [];

    if (host.matches("[data-cms-contact-heading]")) {
        const kicker = host.querySelector(".kicker");
        const title = host.querySelector("h2");

        return [
            kicker && {
                element: kicker,
                type: "text",
                label: "Small Heading"
            },
            title && {
                element: title,
                type: "text",
                label: "Main Heading"
            }
        ].filter(Boolean);
    }

    if (host.matches(".contact-card > .contact-row")) {
        const icon = host.querySelector(":scope > .icon");
        const title = host.querySelector("h3");
        const paragraph = host.querySelector("p");
        const link = paragraph?.querySelector("a[href]");
        const detail = link || paragraph;

        if (!icon || !title || !detail) return [];

        const fields = [
            {
                element: icon,
                type: "contactIcon",
                label: "Choose Icon",
                value: normalizeText(icon.textContent)
            },
            {
                element: title,
                type: "contactHeading",
                label: "Heading",
                value: normalizeText(title.textContent)
            },
            {
                element: detail,
                type: "contactText",
                label: "Description",
                value: normalizeText(detail.textContent)
            }
        ];

        if (link) {
            fields.push({
                element: link,
                type: "contactURL",
                label: link.getAttribute("href")?.startsWith("mailto:")
                    ? "Email Link"
                    : "Google Maps URL",
                value: link.getAttribute("href") || ""
            });
        }

        return fields;
    }

    if (host.matches(".faq > .faq-item")) {
        const question = host.querySelector(".faq-q");
        const answer = host.querySelector(".faq-a");

        if (!question || !answer) return [];

        const questionText = [...question.childNodes]
            .filter(node => node.nodeType === 3)
            .map(node => node.textContent)
            .join(" ")
            .trim();

        return [
            {
                element: question,
                type: "faqQuestion",
                label: "Question",
                value: questionText
            },
            {
                element: answer,
                type: "faqAnswer",
                label: "Answer",
                value: normalizeText(answer.textContent)
            }
        ];
    }

    if (host.matches(".partner-list > div")) {
        const heading = host.querySelector(":scope > strong");

        if (!heading) return [];

        const detail = [...host.childNodes]
            .filter(node => node.nodeType === 3)
            .map(node => node.textContent)
            .join(" ")
            .trim();

        return [
            {
                element: heading,
                type: "text",
                label: "Heading"
            },
            {
                element: host,
                type: "partnerDetail",
                label: "Description",
                value: detail
            }
        ];
    }

    if (host.matches(".partner")) {
        const icon = host.querySelector(":scope > .icon");
        const heading = host.querySelector(":scope > h2");
        const description = host.querySelector(":scope > p");

        return [
            icon && {
                element: icon,
                type: "text",
                label: "Icon"
            },
            heading && {
                element: heading,
                type: "text",
                label: "Heading"
            },
            description && {
                element: description,
                type: "text",
                label: "Description"
            }
        ].filter(Boolean);
    }

    if (host.matches(".map-layout > .locations > .location")) {
        const heading = host.querySelector(":scope > strong");
        const detail = host.querySelector(":scope > span");

        if (!heading || !detail) return [];

        const text = normalizeText(heading.textContent);
        const match = text.match(/^(\S+)\s+([\s\S]+)$/u);
        const icon = match ? match[1] : "📍";
        const title = match ? match[2] : text;
        const link = detail.querySelector("a[href]");
        const target = link || detail;

        const result = [
            {
                element: heading,
                type: "locationIcon",
                label: "Choose Icon",
                value: icon
            },
            {
                element: heading,
                type: "locationHeading",
                label: "Heading",
                value: title
            },
            {
                element: target,
                type: "locationDescription",
                label: link ? "Link Text" : "Description",
                value: normalizeText(target.textContent)
            }
        ];

        if (link) {
            result.push({
                element: link,
                type: "locationURL",
                label: "Google Maps URL",
                value: link.getAttribute("href") || ""
            });
        }

        return result;
    }

    const isPurposeHeading =
        host.matches(".section-head > div") &&
        !!host.querySelector(".features > .feature");

    const isPurposeCard =
        host.matches(".features > .feature");

    const walker = doc.createTreeWalker(
        host,
        doc.defaultView.NodeFilter.SHOW_ELEMENT
    );

    let element;

    while ((element = walker.nextNode())) {
        if (element.closest("[data-cms-editor]")) {
            continue;
        }

        // Prevent the heading editor from editing cards.
        if (
            isPurposeHeading &&
            element.closest(".features")
        ) {
            continue;
        }

        if (element.matches(
            "script,style,button,svg,iframe"
        )) {
            continue;
        }

        // Allow editing the icon on each purpose card.
        if (
            isPurposeCard &&
            element.matches(".icon") &&
            element.textContent.trim()
        ) {
            fields.push({
                element,
                type: "text",
                label: "Icon"
            });

            continue;
        }
        
        if (
            element.matches(".kicker") &&
            host.querySelector(".features > .feature") &&
            host.contains(element)
        ) {
            fields.push({
                element,
                type: "text",
                label: "Small Heading"
            });

            continue;
        }
        
if (
    element.matches(".kicker") &&
    host.querySelector(".features > .feature")
) {
    fields.push({
        element,
        type: "text",
        label: "Small Heading"
    });

    continue;
}

        if (element.matches("img")) {
            fields.push({
                element,
                type: "image",
                label: element.alt || "Image"
            });

            continue;
        }

        const selector =
            "h1,h2,h3,h4,h5,h6,p,small,strong,.tag,.price,span,.kicker";

        if (
            element.matches(selector) &&
            !element.querySelector(selector) &&
            element.textContent.trim()
        ) {
            fields.push({
                element,
                type: "text",
                label: element.tagName.toLowerCase()
            });
        }
    }

    
    if (host.matches(".pagehero.cover-banner")) {
    const background = {
        element: host,
        type: "background",
        label: "Background Image"
    };

    return [background];
}
return fields.slice(0, 80);

}

    // ==========================================
    // FORM INPUTS
    // ==========================================

    function createInput(
        labelText,
        value,
        type = "text",
        imageURL = "",
        options = {}
    ) {
        const wrapper =
            document.createElement("div");

        wrapper.className = "cms-field";

        const label =
            document.createElement("label");

        label.textContent = labelText;
        wrapper.appendChild(label);

        // CMS PRODUCT CATEGORY DROPDOWN
        if (options.categoryDropdown === true) {
            const input = document.createElement("select");

            const categories = [
                ["fruits", "Fruits"],
                ["vegetables", "Vegetables"],
                ["plants", "Plants"],
                ["livestock", "Livestock"],
                ["others", "Others"]
            ];

            const selected = String(value || "")
                .trim()
                .toLowerCase();

            categories.forEach(([key, title]) => {
                const option = document.createElement("option");
                option.value = title;
                option.textContent = title;
                option.selected = key === selected;
                input.appendChild(option);
            });

            input.style.width = "100%";
            input.style.cursor = "pointer";
            wrapper.appendChild(input);

            return { wrapper, input };
        }

        if (type !== "image") {
            const multiline = !!options.multiline;

            const input = document.createElement(
                multiline ? "textarea" : "input"
            );

            if (!multiline) {
                input.type = "text";
            } else {
                input.rows = 3;
                input.className = "cms-description-input";
            }

            input.value = multiline
                ? normalizeText(value)
                : String(value || "").trim();

            input.spellcheck = true;
            input.style.textAlign = "left";
            input.style.textIndent = "0";

            wrapper.appendChild(input);

            return { wrapper, input };
        }

        const pictureArea =
            document.createElement("div");

        pictureArea.className = "cms-picture-area";

        const preview =
            document.createElement("img");

        preview.className = "cms-image-preview";
        preview.alt = "Picture preview";

        const input =
            document.createElement("input");

        input.type = "file";
        input.accept = IMAGE_TYPES.join(",");
        input.className = "cms-picture-file";

        const inputId =
            "cms-picture-" +
            Math.random().toString(36).slice(2);

        input.id = inputId;

        const actions =
            document.createElement("div");

        actions.className = "cms-picture-actions";

        const uploadLabel =
            document.createElement("label");

        uploadLabel.className = "cms-picture-upload";
        uploadLabel.htmlFor = inputId;
        uploadLabel.innerHTML = UPLOAD + "<span>Upload Picture</span>";

        const remove =
            document.createElement("button");

        remove.type = "button";
        remove.className = "cms-picture-remove";
        remove.textContent = "Remove Photo";
        remove.title = "Remove current picture";

        const hint =
            document.createElement("small");

        hint.className = "cms-upload-hint";
        hint.textContent =
            "JPG, PNG, WebP or GIF · Max 5 MB";

        let objectURL = null;
        let removed = false;

        const control = {
            wrapper,
            input,
            get removed() {
                return removed;
            }
        };

        function showPreview(url) {
            if (url) {
                preview.src = url;
                preview.hidden = false;
            } else {
                preview.removeAttribute("src");
                preview.hidden = true;
            }

            pictureArea.classList.toggle(
                "is-empty",
                !url
            );

            remove.disabled = !url;
        }

        showPreview(imageURL);

        input.addEventListener("change", () => {
            const file = input.files[0];

            if (!file) return;

            if (!IMAGE_TYPES.includes(file.type)) {
                alert(
                    "Only JPG, PNG, WebP, and GIF images are allowed."
                );

                input.value = "";
                showPreview(removed ? "" : imageURL);
                return;
            }

            if (file.size > MAX_IMAGE_SIZE) {
                alert(
                    "Picture must not exceed 5 MB."
                );

                input.value = "";
                showPreview(removed ? "" : imageURL);
                return;
            }

            if (objectURL) {
                URL.revokeObjectURL(objectURL);
                imageURLs.delete(objectURL);
            }

            objectURL = URL.createObjectURL(file);
            imageURLs.add(objectURL);

            removed = false;
            showPreview(objectURL);
        });

        remove.addEventListener("click", () => {
            input.value = "";
            removed = true;

            if (objectURL) {
                URL.revokeObjectURL(objectURL);
                imageURLs.delete(objectURL);
                objectURL = null;
            }

            showPreview("");
        });

        pictureArea.appendChild(preview);
        actions.append(uploadLabel, remove);

        wrapper.append(
            pictureArea,
            input,
            actions,
            hint
        );

        return control;
    }

    function resetImageURLs() {
        imageURLs.forEach(url => {
            URL.revokeObjectURL(url);
        });

        imageURLs.clear();
    }

    // ==========================================
    // WIZARD
    // ==========================================

    function createWizard(
        formId,
        fieldsId,
        pages,
        kind
    ) {
        const form = $(formId);
        const container = $(fieldsId);

        container.replaceChildren();

        const isSlide = kind === "slide";

        const progress =
            document.createElement("div");

        progress.className = "cms-wizard-progress";

        const progressText =
            document.createElement("span");

        const track =
            document.createElement("div");

        track.className = "cms-progress-track";

        const fill =
            document.createElement("div");

        fill.className = "cms-progress-fill";

        track.appendChild(fill);
        progress.append(progressText, track);

        const area =
            document.createElement("div");

        area.className = "cms-wizard-pages";

        pages.forEach(page => {
            page.classList.add("cms-wizard-page");
            area.appendChild(page);
        });

        container.appendChild(area);

        const actions =
            form.querySelector(".cms-dialog-actions");

        actions.querySelectorAll(
            ".cms-wizard-back,.cms-wizard-next"
        ).forEach(element => element.remove());

        const back =
            document.createElement("button");

        back.type = "button";
        back.className = "cms-wizard-back";
        back.textContent = "Back";

        const next =
            document.createElement("button");

        next.type = "button";
        next.className = "cms-wizard-next";
        next.textContent = "Next";

        const save =
            form.querySelector('[type="submit"]');

        save.textContent = "Save Changes";

        actions.insertBefore(back, save);
        actions.insertBefore(next, save);

        function render() {
            const index = isSlide
                ? slidePage
                : editPage;

            pages.forEach((page, i) => {
                page.hidden = i !== index;
            });

            if (!isSlide) {
                progressText.textContent =
                    `Step ${index + 1} of ${pages.length}`;

                fill.style.width =
                    `${((index + 1) / pages.length) * 100}%`;
            }

            back.hidden = index === 0;
            next.hidden = index === pages.length - 1;
            save.hidden = index !== pages.length - 1;

            const dialog =
                form.closest(".cms-dialog");

            if (dialog) {
                dialog.scrollTop = 0;
            }
        }

        back.onclick = () => {
            if (isSlide) {
                slidePage = Math.max(0, slidePage - 1);
            } else {
                editPage = Math.max(0, editPage - 1);
            }

            render();
        };

        next.onclick = () => {
            if (isSlide) {
                slidePage = Math.min(
                    pages.length - 1,
                    slidePage + 1
                );
            } else {
                editPage = Math.min(
                    pages.length - 1,
                    editPage + 1
                );
            }

            render();
        };

        render();
    }

    // ==========================================
    // EDIT CONTAINER
    // ==========================================
    
function createIconPicker(value) {
    // The existing icon picker remains in use.
    const icons = [
        "📍", "📌", "🗺️", "🧭",
        "🕘", "⏰", "📅", "🚜",
        "🚗", "☎", "📞", "✉️",
        "🌱", "🌿", "🍃", "🌾",
        "🌻", "🌳", "♻️", "🌍",
        "☀️", "💧", "🐄", "🐓",
        "🐐", "🐇", "🐖", "🥚",
        "🥬", "🥕", "🍅", "🌽",
        "🤝", "💚", "⭐", "✓",
        "🏡", "🚜", "🌼", "🌺"
    ];

    const wrapper = document.createElement("div");
    wrapper.className = "cms-field";

    const label = document.createElement("label");
    label.textContent = "Choose Icon";

    const preview = document.createElement("div");
    preview.style.cssText = `
        font-size: 30px;
        padding: 12px;
        text-align: center;
        border: 1px solid #d3dfd2;
        border-radius: 10px;
        margin: 10px 0;
    `;

    const grid = document.createElement("div");
    grid.style.cssText = `
        display: grid;
        grid-template-columns: repeat(
            auto-fit, minmax(45px, 1fr)
        );
        gap: 8px;
        margin-bottom: 12px;
    `;

    // Compatible with the existing Save Changes logic.
    const input = document.createElement("input");
    input.type = "hidden";
    input.value = value || "🌱";

    const buttons = [];

    function selectIcon(icon) {
        input.value = icon;
        preview.textContent = icon;

        buttons.forEach(button => {
            const selected = button.dataset.icon === icon;

            button.style.borderColor = selected
                ? "#28683d"
                : "#d3dfd2";

            button.style.background = selected
                ? "#e8f3e9"
                : "#ffffff";
        });
    }

    if (input.value && !icons.includes(input.value)) {
        icons.unshift(input.value);
    }

    icons.forEach(icon => {
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = icon;
        button.dataset.icon = icon;
        button.title = icon;

        button.style.cssText = `
            font-size: 23px;
            min-height: 45px;
            border: 1px solid #d3dfd2;
            border-radius: 9px;
            background: white;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        `;

        button.addEventListener("click", () => {
            selectIcon(icon);
        });

        buttons.push(button);
        grid.appendChild(button);
    });

    wrapper.append(label, preview, grid, input);

    selectIcon(input.value);

    return { wrapper, input };
}


    // CMS ABOUT TEXT POLISH V1
    function isAboutTextEditor(host) {
        return host.matches(".about > div:not(.about-img)") &&
            !!host.querySelector(".kicker,h2") &&
            !!host.querySelector(".stats");
    }

    function polishAboutTextPopup() {
        const modal = $("editModal");
        const form = $("editForm");
        const header = modal?.querySelector(".cms-dialog-header");
        const cancel = $("editCancel");

        if (!modal || !form || !header || !cancel) return;

        cancel.style.display = "none";
        header.style.position = "relative";
        header.style.paddingRight = "48px";

        let close = header.querySelector(".cms-about-red-close");

        if (!close) {
            close = document.createElement("button");
            close.type = "button";
            close.className = "cms-about-red-close";
            close.textContent = "×";
            close.title = "Close";
            close.setAttribute("aria-label", "Close About editor");
            header.appendChild(close);
        }

        close.style.cssText = [
            "position:absolute",
            "top:0",
            "right:0",
            "width:36px",
            "height:36px",
            "display:flex",
            "align-items:center",
            "justify-content:center",
            "background:#fff0f0",
            "color:#c62828",
            "border:2px solid #e9aaaa",
            "border-radius:9px",
            "font:700 25px Arial,sans-serif",
            "cursor:pointer",
            "z-index:10"
        ].join(";");

        const initial = [...form.querySelectorAll(
            "input,textarea,select"
        )].map(input => ({
            input,
            value: input.value
        }));

        close.onclick = () => {
            const changed = initial.some(item =>
                item.input.type === "file"
                    ? item.input.files.length > 0
                    : item.input.value !== item.value
            );

            if (changed && !confirm(
                "Discard unsaved About Our Farm changes?"
            )) {
                return;
            }

            modal.hidden = true;
            selectedHost = null;
            resetImageURLs();
        };

        const actions = form.querySelector(".cms-dialog-actions");

        if (actions) {
            actions.style.display = "flex";
            actions.style.alignItems = "center";
            actions.style.justifyContent = "space-between";

            const back = actions.querySelector(".cms-wizard-back");
            const next = actions.querySelector(".cms-wizard-next");
            const save = actions.querySelector('[type="submit"]');

            if (back) back.style.marginRight = "auto";

            if (next) {
                next.style.marginLeft = "auto";
                next.style.background = "#315b36";
                next.style.color = "#ffffff";
                next.style.borderColor = "#315b36";
            }

            if (save) save.style.marginLeft = "auto";
        }
    }
    function openEdit(host) {
        if (previewMode || busy) return;

        resetImageURLs();

        selectedHost = host;
        editPage = 0;

        activeFields = editableFields(host);

        // CMS ABOUT STAT FIELD LABELS V1
        if (isAboutTextEditor(host)) {
            const stats = host.querySelector(".stats");

            if (stats) {
                const statFields = activeFields.filter(field =>
                    stats.contains(field.element)
                );

                statFields.forEach((field, index) => {
                    const number = Math.floor(index / 2) + 1;
                    const kind = index % 2 === 0
                        ? "Heading"
                        : "Description";

                    field.label = `Stat ${number} — ${kind}`;
                });
            }
        }

        if (isAboutTextEditor(host)) {
            const stats = host.querySelector(".stats");
            const isStat = field =>
                stats && stats.contains(field.element);

            const intro = activeFields.filter(field =>
                !isStat(field)
            );

            const statFields = activeFields.filter(isStat);

            activeFields = [...intro, ...statFields];
        }

        if (!activeFields.length) {
            notify("No editable content found.", true);
            return;
        }

        $("editTitle").textContent =
            host.matches(".faq-item")
                ? "Edit FAQ"
                : host.matches(".partner-list > div")
                ? "Edit Partnership Item"
                : host.matches(".partner")
                ? "Edit Partnership"
                : host.matches(".product[data-category]")
                ? "Edit Product"
                : "Edit Container";

        const pages = [];

        const isProductEditor =
            host.matches(".product[data-category]");
        let page = null;
        let count = 0;

        activeFields.forEach((field, index) => {
            if (
                !page ||
                (isAboutTextEditor(host)
                    ? (index === activeFields.findIndex(f =>
                        host.querySelector(".stats")?.contains(f.element)
                    ) && count > 0)
                    : count >= 4) ||
                (field.type === "image" && count > 0)
            ) {
                page = document.createElement("div");

                const heading =
                    document.createElement("h3");

               const featureCards = [
    ...doc.querySelectorAll(
        ".features > .feature"
    )
];

const featureIndex = featureCards.indexOf(host);

heading.textContent = "";
heading.style.display = "none";

                page.appendChild(heading);
                pages.push(page);
                count = 0;
            }

           
const isSpecialTextField = [
    "faqQuestion",
    "faqAnswer",
    "partnerDetail"
].includes(field.type);

const isIconField =
    field.type === "contactIcon" ||
    field.type === "locationIcon" ||
    (
        field.type === "text" &&
        field.element.matches(".feature > .icon")
    );

const control = isIconField
    ? createIconPicker(
        field.type === "locationIcon"
            ? field.value
            : normalizeText(field.element.textContent)
    )
    : createInput(
        (isSpecialTextField || (
            isAboutTextEditor(host) &&
            field.label?.startsWith("Stat ")
        ))
    ? field.label
    : field.type.startsWith("contact")
    ? field.label
    : field.type.startsWith("location")
    ? field.label
    : field.type === "background"
    ? "Background Image"
    : field.type === "image"
    ? `Replace ${field.label}`
    : field.element.matches(".icon")
            ? "Icon"
    : field.element.matches(".kicker")
            ? "Small Heading"
    : field.element.matches("h1,h2,h3,h4,h5,h6")
            ? "Heading"
    : field.element.matches("p")
            ? "Description"
    : field.element.matches(".price")
            ? "Price"
     : field.element.matches(".tag")
            ? "Category"
            : "Text",
        isSpecialTextField
            ? field.value
            : field.type.startsWith("contact")
            ? field.value
            : field.type.startsWith("location")
            ? field.value
            : field.type === "text"
            ? normalizeText(field.element.textContent)
            : "",
        
        field.type === "background" ? "image" : field.type,
        field.type === "background"
    ? (
        doc.defaultView
            .getComputedStyle(field.element)
            .backgroundImage
            .match(/url\(["']?(.*?)["']?\)/)?.[1] || ""
    )
    : field.type === "image"
        ? field.element.src
        : "",

        {
            multiline:
                field.type === "faqAnswer" ||
                field.type === "partnerDetail" ||
                (
                    field.type === "text" &&
                    field.element.matches("p")
                ),

            categoryDropdown:
                host.matches(".product[data-category]") &&
                field.type === "text" &&
                field.element.matches(".tag")
        }
    );

            field.input = control.input;
            field.control = control;

            if (isProductEditor && field.type === "image") {
                control.wrapper.classList.add("cms-product-square");
            }

            page.appendChild(control.wrapper);
            count++;
        });

        createWizard(
            "editForm",
            "editFields",
            pages,
            "edit"
        );

        if (isAboutTextEditor(host)) {
            $("editTitle").textContent = "Edit About Our Farm";
            polishAboutTextPopup();
        } else {
            $("editCancel").style.display = "";
            const oldClose = $("editModal").querySelector(
                ".cms-about-red-close"
            );
            if (oldClose) oldClose.remove();
        }

        $("editModal").hidden = false;
    }

    $("editCancel").onclick = () => {
        $("editModal").hidden = true;
        selectedHost = null;
        resetImageURLs();
    };

    // ==========================================
    // IMAGE UPLOAD
    // ==========================================

    async function uploadImage(file) {
        if (!IMAGE_TYPES.includes(file.type)) {
            throw new Error(
                "Unsupported image format."
            );
        }

        if (file.size > MAX_IMAGE_SIZE) {
            throw new Error(
                "Maximum image size is 5 MB."
            );
        }

        const data = new FormData();

        data.append("image", file);
        data.append("csrf", window.CMS_TOKEN || "");

        const response = await fetch("upload.php", {
            method: "POST",
            body: data
        });

        const result = await response.json();

        if (!response.ok || !result.ok) {
            throw new Error(
                result.error || "Image upload failed."
            );
        }

        return result.path;
    }

    // ==========================================
    // SAVE CONTAINER
    // ==========================================

    $("editForm").onsubmit = async event => {
        event.preventDefault();

        if (!selectedHost || busy || previewMode) {
            return;
        }

        busy = true;
        updateButtons();

        const submit =
            $("editForm").querySelector('[type="submit"]');

        submit.disabled = true;

        try {
            const changes = [];

            for (const field of activeFields) {
                if (field.type === "contactIcon") {
                    changes.push({
                        element: field.element,
                        kind: "text",
                        before: field.element.textContent,
                        after: field.input.value.trim()
                    });
                    continue;
                }

                if (
                    field.type === "contactHeading" ||
                    field.type === "contactText"
                ) {
                    changes.push({
                        element: field.element,
                        kind: "text",
                        before: field.element.textContent,
                        after: field.input.value.trim()
                    });
                    continue;
                }

                if (field.type === "contactURL") {
                    const url = field.input.value.trim();
                    const original = field.element.getAttribute("href") || "";

                    if (original.startsWith("mailto:")) {
                        if (!/^mailto:[^\s@]+@[^\s@]+\.[^\s@]+$/i.test(url)) {
                            throw new Error("Enter a valid mailto: email link.");
                        }
                    } else {
                        let parsed;
                        try {
                            parsed = new URL(url);
                        } catch {
                            throw new Error("Enter a valid Google Maps URL.");
                        }

                        const hostname = parsed.hostname.toLowerCase();

                        if (
                            parsed.protocol !== "https:" ||
                            !(
                                hostname === "google.com" ||
                                hostname.endsWith(".google.com") ||
                                hostname === "maps.app.goo.gl" ||
                                hostname === "goo.gl"
                            )
                        ) {
                            throw new Error("Use a valid HTTPS Google Maps URL.");
                        }
                    }

                    changes.push({
                        element: field.element,
                        kind: "href",
                        before: original,
                        after: url
                    });
                    continue;
                }
                if (field.type === "faqQuestion") {
                    const question = field.input.value.trim();

                    if (!question) {
                        throw new Error("Question cannot be empty.");
                    }

                    changes.push({
                        element: field.element,
                        kind: "faqQuestion",
                        before: field.value,
                        after: question
                    });
                    continue;
                }

                if (field.type === "faqAnswer") {
                    changes.push({
                        element: field.element,
                        kind: "text",
                        before: field.element.textContent,
                        after: field.input.value.trim()
                    });
                    continue;
                }

                if (field.type === "partnerDetail") {
                    changes.push({
                        element: field.element,
                        kind: "partnerDetail",
                        before: field.value,
                        after: field.input.value.trim()
                    });
                    continue;
                }

                if (field.type === "locationIcon") {
                    continue;
                }

                if (field.type === "locationHeading") {
                    const iconField = activeFields.find(
                        f => f.type === "locationIcon"
                    );

                    const icon = (
                        iconField?.input.value || "📍"
                    ).trim();

                    const title = field.input.value.trim();

                    if (!title) {
                        throw new Error("Heading cannot be empty.");
                    }

                    changes.push({
                        element: field.element,
                        kind: "text",
                        before: field.element.textContent,
                        after: `${icon} ${title}`
                    });

                    continue;
                }

                if (field.type === "locationDescription") {
                    changes.push({
                        element: field.element,
                        kind: "text",
                        before: field.element.textContent,
                        after: field.input.value.trim()
                    });

                    continue;
                }

                if (field.type === "locationURL") {
                    const url = field.input.value.trim();

                    let parsed;

                    try {
                        parsed = new URL(url);
                    } catch {
                        throw new Error(
                            "Enter a valid Google Maps URL."
                        );
                    }

                    const hostname = parsed.hostname.toLowerCase();

                    const validHost =
                        hostname === "google.com" ||
                        hostname.endsWith(".google.com") ||
                        hostname === "maps.app.goo.gl" ||
                        hostname === "goo.gl";

                    if (
                        parsed.protocol !== "https:" ||
                        !validHost
                    ) {
                        throw new Error(
                            "Use a valid HTTPS Google Maps URL."
                        );
                    }

                    changes.push({
                        element: field.element,
                        kind: "href",
                        before: field.element.getAttribute("href"),
                        after: url
                    });

                    continue;
                }

                if (field.type === "background") {
                    const file = field.input.files[0];

                    if (!file && !field.control.removed) {
                        continue;
                    }

                    const path = file
                        ? await uploadImage(file)
                        : null;

                    const value = path
                        ? `url("${path.replaceAll('"', "%22")}")`
                        : "none";

                    changes.push({
                        element: field.element,
                        kind: "background",
                        before:
                            field.element.style.backgroundImage,
                        after: value
                    });

                    continue;
                }

                if (field.type === "text") {
                    changes.push({
                        element: field.element,
                        kind: "text",
                        before: field.element.textContent,
                        after: field.input.value
                    });

                } else if (field.input.files[0]) {
                    const path = await uploadImage(
                        field.input.files[0]
                    );

                    changes.push({
                        element: field.element,
                        kind: "src",
                        before:
                            field.element.getAttribute("src"),
                        after: path
                    });

                } else if (field.control.removed) {
                    changes.push({
                        element: field.element,
                        kind: "src",
                        before:
                            field.element.getAttribute("src"),
                        after: null
                    });
                }
            }

            if (!applyChanges(changes, true)) {
                throw new Error(
                    "Could not apply container changes."
                );
            }

            recordChanges(changes);

            $("editModal").hidden = true;
            selectedHost = null;

            resetImageURLs();

            notify("Container updated successfully.");

        } catch (error) {
            notify(error.message, true);
            alert(error.message);

        } finally {
            busy = false;
            submit.disabled = false;
            updateButtons();
            queueVisibilityCheck();
        }
    };

    // ==========================================
    // SLIDESHOW HEADING
    // ==========================================

    function getHeadingParts(element) {
        if (!element) {
            return {
                first: "",
                second: "",
                mode: "none"
            };
        }

        const html = element.innerHTML;

        if (/<br\s*\/?>/i.test(html)) {
            const pieces = html.split(/<br\s*\/?>/i);

            const temporary =
                document.createElement("div");

            temporary.innerHTML = pieces[0] || "";
            const first =
                normalizeText(temporary.textContent);

            temporary.innerHTML =
                pieces.slice(1).join(" ");

            const second =
                normalizeText(temporary.textContent);

            return {
                first,
                second,
                mode: "br"
            };
        }

        const children = [...element.children].filter(
            child => child.matches(
                "span,strong,em,b,i"
            )
        );

        if (children.length >= 2) {
            return {
                first:
                    normalizeText(children[0].textContent),
                second:
                    normalizeText(children[1].textContent),
                mode: "children",
                children
            };
        }

        const text = element.textContent.trim();

        if (text.includes("\n")) {
            const pieces = text.split(/\n+/);

            return {
                first: normalizeText(pieces[0]),
                second:
                    normalizeText(pieces.slice(1).join(" ")),
                mode: "newline"
            };
        }

        return {
            first: normalizeText(text),
            second: "",
            mode: "single"
        };
    }

    function buildHeadingHTML(
        element,
        first,
        second,
        headingInfo
    ) {
        if (
            headingInfo.mode === "children" &&
            headingInfo.children?.length >= 2
        ) {
            const clone = element.cloneNode(true);

            const children = [...clone.children].filter(
                child => child.matches(
                    "span,strong,em,b,i"
                )
            );

            if (children[0]) {
                children[0].textContent = first;
            }

            if (children[1]) {
                children[1].textContent = second;
            }

            return clone.innerHTML;
        }

        if (!second.trim()) {
            return escapeHTML(first);
        }

        return (
            escapeHTML(first) +
            "<br>" +
            escapeHTML(second)
        );
    }

    // ==========================================
    // MANAGE SLIDESHOW
    // ==========================================

    function openSlideshow() {
        if (previewMode || busy) return;

        resetImageURLs();

        slidePage = 0;
        slideFields = [];

        const slides = [
            ...doc.querySelectorAll(".hero .slide")
        ];

        if (!slides.length) {
            notify("No slideshow slides found.", true);
            return;
        }

        const modal = $("slidesModal");

        const modalTitle = modal.querySelector(
            ".cms-dialog-header h2"
        );

        if (modalTitle) {
            modalTitle.textContent = "Manage Slideshow";
        }

        const subtitle = modal.querySelector(
            ".cms-dialog-header p"
        );

        if (subtitle) {
            subtitle.hidden = true;
        }

        const pages = [];

        slides.forEach((slide, index) => {
            const page =
                document.createElement("div");

            const heading =
                document.createElement("h3");

            heading.textContent = `Slide ${index + 1}`;
            page.appendChild(heading);

            const bg =
                slide.querySelector(".slide-bg");

            const kicker =
                slide.querySelector(".kicker");

            const title =
                slide.querySelector("h1,h2");

            const description =
                slide.querySelector(".hero-copy p");

            const background = bg
                ? doc.defaultView
                    .getComputedStyle(bg)
                    .backgroundImage
                : "";

            const imageURL =
                background.match(
                    /url\(["']?(.*?)["']?\)/
                )?.[1] || "";

            const image = createInput(
                "Slide Image",
                "",
                "image",
                imageURL
            );

            page.appendChild(image.wrapper);

            const kickerControl = createInput(
                "Small Heading",
                normalizeText(kicker?.textContent)
            );

            const headingInfo =
                getHeadingParts(title);

            const title1Control = createInput(
                "Main Heading 1",
                headingInfo.first
            );

            const title2Control = createInput(
                "Main Heading 2",
                headingInfo.second
            );

            const descriptionControl = createInput(
                "Description",
                normalizeText(description?.textContent),
                "text",
                "",
                { multiline: true }
            );

            [
                kickerControl,
                title1Control,
                title2Control,
                descriptionControl
            ].forEach(control => {

            page.appendChild(control.wrapper);
            });

            pages.push(page);

            slideFields.push({
                bg,
                kicker,
                title,
                description,
                headingInfo,

                imageInput: image.input,
                imageControl: image,

                kickerInput: kickerControl.input,
                title1Input: title1Control.input,
                title2Input: title2Control.input,
                descriptionInput:
                    descriptionControl.input
            });
        });

        createWizard(
            "slidesForm",
            "slidesFields",
            pages,
            "slide"
        );

        setupSlideshowClose();
        modal.hidden = false;
    }

    // CMS SLIDESHOW RED CLOSE V2
    function setupSlideshowClose() {
        const modal = $("slidesModal");
        const form = $("slidesForm");
        const cancel = $("slidesCancel");
        const header = modal?.querySelector(".cms-dialog-header");

        if (!modal || !form || !header || !cancel) return;

        cancel.style.display = "none";
        header.style.position = "relative";
        header.style.paddingRight = "48px";

        let close = header.querySelector(".cms-slideshow-red-close");

        if (!close) {
            close = document.createElement("button");
            close.type = "button";
            close.className = "cms-slideshow-red-close";
            close.textContent = "×";
            close.title = "Close";
            close.setAttribute("aria-label", "Close slideshow editor");
            header.appendChild(close);
        }

        close.style.cssText = [
            "position:absolute",
            "top:0",
            "right:0",
            "width:36px",
            "height:36px",
            "display:flex",
            "align-items:center",
            "justify-content:center",
            "background:#fff0f0",
            "color:#c62828",
            "border:2px solid #e9aaaa",
            "border-radius:9px",
            "font:700 25px Arial,sans-serif",
            "cursor:pointer",
            "z-index:10"
        ].join(";");

        const actions = form.querySelector(".cms-dialog-actions");

        if (actions) {
            actions.style.display = "flex";
            actions.style.alignItems = "center";
            actions.style.justifyContent = "space-between";
            actions.style.gap = "10px";

            const back = actions.querySelector(".cms-wizard-back");
            const next = actions.querySelector(".cms-wizard-next");
            const save = actions.querySelector('[type="submit"]');

            if (back) back.style.marginRight = "auto";

            if (next) {
                next.style.marginLeft = "auto";
                next.style.background = "#315b36";
                next.style.color = "#ffffff";
                next.style.borderColor = "#315b36";
            }

            if (save) save.style.marginLeft = "auto";
        }

        const initial = [...form.querySelectorAll(
            "input,textarea,select"
        )].map(input => ({
            input,
            value: input.value,
            checked: input.checked
        }));

        close.onclick = () => {
            const changed = initial.some(item =>
                item.input.type === "file"
                    ? item.input.files.length > 0
                    : item.input.value !== item.value ||
                      item.input.checked !== item.checked
            );

            const removed = slideFields.some(field =>
                field.imageControl?.removed
            );

            if ((changed || removed) &&
                !confirm("Discard unsaved slideshow changes?")) {
                return;
            }

            modal.hidden = true;
            resetImageURLs();
        };
    }
    $("slidesCancel").onclick = () => {
        $("slidesModal").hidden = true;
        resetImageURLs();
    };

    // ==========================================
    // SAVE SLIDESHOW
    // ==========================================

    $("slidesForm").onsubmit = async event => {
        event.preventDefault();

        if (busy || previewMode) return;

        busy = true;
        updateButtons();

        const submit =
            $("slidesForm").querySelector(
                '[type="submit"]'
            );

        submit.disabled = true;

        try {
            const uploaded = [];

            for (const field of slideFields) {
                const file = field.imageInput.files[0];

                uploaded.push(
                    file
                        ? await uploadImage(file)
                        : null
                );
            }

            const action = [];

            slideFields.forEach((field, index) => {
                if (field.kicker) {
                    action.push({
                        element: field.kicker,
                        kind: "text",
                        before: field.kicker.textContent,
                        after: field.kickerInput.value
                    });
                }

                if (field.title) {
                    const first =
                        field.title1Input.value.trim();

                    const second =
                        field.title2Input.value.trim();

                    action.push({
                        element: field.title,
                        kind: "html",
                        before: field.title.innerHTML,
                        after: buildHeadingHTML(
                            field.title,
                            first,
                            second,
                            field.headingInfo
                        )
                    });
                }

                if (field.description) {
                    action.push({
                        element: field.description,
                        kind: "text",
                        before:
                            field.description.textContent,
                        after: normalizeText(
                            field.descriptionInput.value
                        )
                    });
                }

                if (field.bg) {
                    if (uploaded[index]) {
                        const path = uploaded[index];

                        action.push({
                            element: field.bg,
                            kind: "background",
                            before:
                                field.bg.style.backgroundImage,
                            after:
                                `url("${path.replaceAll('"', "%22")}")`
                        });

                    } else if (
                        field.imageControl.removed
                    ) {
                        action.push({
                            element: field.bg,
                            kind: "background",
                            before:
                                field.bg.style.backgroundImage,
                            after: "none"
                        });
                    }
                }
            });

            if (!applyChanges(action, true)) {
                throw new Error(
                    "Could not apply slideshow changes."
                );
            }

            recordChanges(action);

            $("slidesModal").hidden = true;

            resetImageURLs();

            notify("Slideshow updated successfully.");

        } catch (error) {
            notify(error.message, true);
            alert(error.message);

        } finally {
            busy = false;
            submit.disabled = false;

            updateButtons();
            queueVisibilityCheck();
        }
    };

    // ==========================================
    // PREVIEW MODE
    // ==========================================

    function applyPreviewMode() {
        if (!doc?.documentElement) return;

        doc.documentElement.classList.toggle(
            "cms-editor-preview",
            previewMode
        );

        if ($("previewNotice")) {
            $("previewNotice").hidden = !previewMode;
        }

        if ($("previewLabel")) {
            $("previewLabel").textContent =
                previewMode
                    ? "Exit Preview"
                    : "Preview";
        }

        if ($("previewMode")) {
            $("previewMode").setAttribute(
                "aria-pressed",
                String(previewMode)
            );
        }

        if (previewMode) {
            $("editModal").hidden = true;
            $("slidesModal").hidden = true;
        }

        updateButtons();
        queueVisibilityCheck();
    }

    $("previewMode").onclick = () => {
        if (!ready || busy) return;

        previewMode = !previewMode;
        applyPreviewMode();
    };

    $("exitPreview").onclick = () => {
        previewMode = false;
        applyPreviewMode();
    };

    // ==========================================
    // UNDO
    // ==========================================

    $("undo").onclick = () => {
        if (
            !ready ||
            previewMode ||
            busy ||
            historyIndex < 0
        ) {
            return;
        }

        if (
            applyChanges(
                history[historyIndex],
                false
            )
        ) {
            historyIndex--;
            updateButtons();
            notify("Change undone.");
        }
    };

    // ==========================================
    // REDO
    // ==========================================

    $("redo").onclick = () => {
        if (
            !ready ||
            previewMode ||
            busy ||
            historyIndex >= history.length - 1
        ) {
            return;
        }

        if (
            applyChanges(
                history[historyIndex + 1],
                true
            )
        ) {
            historyIndex++;
            updateButtons();
            notify("Change redone.");
        }
    };

    // ==========================================
    // SERVER REQUESTS
    // ==========================================

    function sendSlideCommand(command) {
        frame.contentWindow?.postMessage(
            { cmsSlideCommand: command },
            location.origin
        );
    }

    async function postJSON(url, payload = {}) {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token":
                    window.CMS_TOKEN || ""
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!response.ok || !result.ok) {
            throw new Error(
                result.error || "Request failed."
            );
        }

        return result;
    }

    // ==========================================
    // SAVE DRAFT
    // ==========================================

    async function saveDraft() {
        if (!ready) {
            throw new Error("Website not ready.");
        }

        const html = cleanHTML();

        const parsed =
            new DOMParser().parseFromString(
                html,
                "text/html"
            );

        if (
            !parsed.body ||
            !parsed.body.children.length ||
            !parsed.querySelector("head")
        ) {
            throw new Error(
                "Cannot save an empty website preview."
            );
        }

        await postJSON("save.php", { html });

        savedHTML = html;
        hasDraft = true;

        history = [];
        historyIndex = -1;

        updateButtons();
    }

    $("saveDraft").onclick = async () => {
        if (busy || previewMode) return;

        busy = true;
        updateButtons();

        try {
            await saveDraft();
            notify("Draft saved successfully.");

        } catch (error) {
            notify(error.message, true);
            alert(error.message);

        } finally {
            busy = false;
            updateButtons();
            queueVisibilityCheck();
        }
    };

    // ==========================================
    // PUBLISH
    // ==========================================

    $("publishAll").onclick = () => {
        if (!ready || busy || previewMode) {
            return;
        }

        $("publishConfirm").hidden = false;
    };

    $("publishNo").onclick = () => {
        $("publishConfirm").hidden = true;
    };

    $("publishYes").onclick = async () => {
        if (busy || previewMode) return;

        $("publishConfirm").hidden = true;

        busy = true;
        updateButtons();

        try {
            await saveDraft();

            const result =
                await postJSON("publish.php");

            hasDraft = !!result.warning;
            savedHTML = cleanHTML();

            updateButtons();

            alert(
                result.warning ||
                "Website published successfully."
            );

        } catch (error) {
            notify(error.message, true);
            alert(error.message);

        } finally {
            busy = false;
            updateButtons();
            queueVisibilityCheck();
        }
    };

    // ==========================================
    // UNSAVED CHANGES
    // ==========================================

    window.addEventListener(
        "beforeunload",
        event => {
            if (
                ready &&
                cleanHTML() !== savedHTML
            ) {
                event.preventDefault();
                event.returnValue = "";
            }
        }
    );

    // ==========================================
    // PERSISTENT CHANGE HISTORY
    // ==========================================

    const historyOverlay = $("cmsHistoryOverlay");
    const historyList = $("cmsHistoryList");
    const historyDetails = $("cmsHistoryDetails");
    const historyButton = $("cmsHistoryButton");
    let historyEntries = [];
    let historySelectedId = null;
    let historyRequestSerial = 0;

    function historyPrettyDate(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return "Saved version";
        return new Intl.DateTimeFormat("en-PH", {
            dateStyle: "medium", timeStyle: "short"
        }).format(date);
    }

    function historyBackToList() {
        historyRequestSerial++;
        historySelectedId = null;
        historyDetails.hidden = true;
        historyDetails.replaceChildren();
        historyList.hidden = false;
    }

    function historyInstallPolish() {
        if (document.getElementById("cms-history-polish-v2")) return;
        const style = document.createElement("style");
        style.id = "cms-history-polish-v2";
        style.textContent = `
            #cmsHistoryOverlay { font-family: Poppins, Arial, sans-serif; }
            #cmsHistoryOverlay .cms-history-entry {
                border: 1px solid #e0e9df; border-radius: 13px;
                background: #fff; padding: 15px 17px;
                display: flex; flex-wrap: wrap; align-items: center;
                column-gap: 12px; row-gap: 5px;
                box-shadow: 0 2px 8px rgba(25,65,36,.035);
                transition: border-color .16s, box-shadow .16s;
            }
            #cmsHistoryOverlay .cms-history-entry:hover {
                border-color: #b9d2bc; box-shadow: 0 5px 18px rgba(25,65,36,.07);
            }
            #cmsHistoryOverlay .cms-history-entry-title {
                flex: 1 1 240px; font-size: 13px; font-weight: 650;
                color: #214e30; min-width: 0;
            }
            #cmsHistoryOverlay .cms-history-entry-meta {
                font-size: 11px; color: #728374; flex: 1 1 140px;
                overflow-wrap: anywhere;
            }
            #cmsHistoryOverlay .cms-history-entry > button {
                border: 1px solid #bdd4c0; background: #f5faf5;
                color: #275936; border-radius: 9px; padding: 9px 13px;
                font: 600 11px Poppins, Arial, sans-serif;
                cursor: pointer; transition: background .15s;
            }
            #cmsHistoryOverlay .cms-history-entry > button:hover { background: #e7f3e8; }
            #cmsHistoryOverlay .cms-history-details-header {
                display: flex; justify-content: space-between; align-items: center;
                flex-wrap: wrap; gap: 10px; margin: 0 0 12px;
                border-bottom: 1px solid #e4ede3; padding-bottom: 12px;
            }
            #cmsHistoryOverlay .cms-history-details-header h3 {
                margin: 0; font-size: 16px; font-weight: 700; color: #255637;
            }
            #cmsHistoryOverlay .cms-history-back {
                display: inline-flex; align-items: center; gap: 7px;
                background: #f4f8f3; border: 1px solid #c8d9c7;
                color: #285936; border-radius: 9px; padding: 9px 13px;
                font: 600 11px Poppins, Arial, sans-serif; cursor: pointer;
            }
            #cmsHistoryOverlay .cms-history-back:hover { background: #e5f1e5; }
            #cmsHistoryOverlay .cms-history-change-card {
                border: 1px solid #e0e9df; border-radius: 12px;
                background: #fff; padding: 12px 14px; margin: 9px 0;
            }
            #cmsHistoryOverlay .cms-history-change-card > summary {
                cursor: pointer; font-weight: 600; color: #29583a;
            }
            #cmsHistoryOverlay .cms-history-field-pair {
                display: grid; grid-template-columns: repeat(2,minmax(0,1fr));
                gap: 10px; margin: 8px 0 14px;
            }
            #cmsHistoryOverlay .cms-history-field-pair > div {
                min-width: 0; border-radius: 9px; padding: 10px 12px;
                border: 1px solid #e1e9e0; background: #f8faf8;
                overflow-wrap: anywhere;
            }
            #cmsHistoryOverlay .cms-history-field-pair > div:last-child {
                border-color: #cce2cf; background: #f1f9f2;
            }
            #cmsHistoryOverlay .cms-history-field-pair p {
                white-space: pre-wrap; overflow-wrap: anywhere;
            }
            #cmsHistoryOverlay .cms-history-restore {
                background: #315b36; color: white; border: 0;
                border-radius: 9px; padding: 11px 15px;
                font: 600 12px Poppins, Arial, sans-serif;
                cursor: pointer; margin-top: 14px;
            }
            #cmsHistoryOverlay .cms-history-restore:hover { background: #244a2a; }
            @media(max-width:600px) {
                #cmsHistoryOverlay .cms-history-field-pair { grid-template-columns: 1fr; }
                #cmsHistoryOverlay .cms-history-entry > button { width: 100%; }
            }
        `;
        document.head.appendChild(style);
    }

    const historyEscape = value => String(value ?? "").replace(/[&<>"']/g, ch => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[ch]);

    async function historyRequest(action, data = null, id = "") {
        const options = data === null ? {} : {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": window.CMS_TOKEN || "" },
            body: JSON.stringify(data)
        };
        const response = await fetch("history.php?action=" + encodeURIComponent(action) + (id ? "&id=" + encodeURIComponent(id) : ""), options);
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || "History request failed.");
        return result;
    }

    // Extract comparable fields from stable, visible website containers.
    function historySnapshot(html) {
        const doc = new DOMParser().parseFromString(html || "", "text/html");
        const records = new Map();
        const clean = value => String(value || "").replace(/\s+/g, " ").trim();
        const txt = (root, selector) => clean(root?.querySelector(selector)?.textContent);
        const add = (group, item, field, value) => {
            const key = [group, item, field].join("::");
            records.set(key, {group, item, field, value: clean(value)});
        };
        const addText = (group, item, root, selectors) => {
            if (!root) return;
            for (const [field, selector] of Object.entries(selectors)) {
                const el = root.querySelector(selector);
                if (el) add(group, item, field, el.textContent);
            }
        };
        const each = (selector, callback) => doc.querySelectorAll(selector).forEach(callback);
        each(".hero .slide", (el, i) => {
            const name = "Slide " + (i + 1);
            addText("Homepage Slideshow", name, el, {Kicker: ".kicker", Heading: "h1", Description: ".hero-copy p"});
            add("Homepage Slideshow", name, "Background image", el.querySelector(".slide-bg")?.style.backgroundImage || "");
            el.querySelectorAll(".actions a").forEach((a, n) => add("Homepage Slideshow", name, "Button " + (n + 1), clean(a.textContent) + " → " + a.getAttribute("href")));
        });
        addText("Home", "Welcome", doc.querySelector(".welcome"), {Heading:"h2", Description:"p", Button:"a"});
        const features = [...doc.querySelectorAll(".features .feature")];
        features.forEach((el, i) => addText("Home", "Feature " + (i+1), el, {Title:"h3", Description:"p", Icon:".icon"}));
        const about = [...doc.querySelectorAll(".container.about")];
        if (about[0]) {
            addText("Home", "About Our Farm", about[0], {Heading:"h2", Description:"p", Button:"a.btn"});
            add("Home", "About Our Farm", "Image", about[0].querySelector(".about-img img")?.getAttribute("src") || "");
            about[0].querySelectorAll(".stat").forEach((el,i)=>add("Home", "Statistic " + (i+1), "Text", el.textContent));
        }
        each(".two article.box", (el,i)=>addText("Farm Info", i===0?"Mission":"Vision", el, {Heading:"h3", Description:"p", Icon:".icon"}));
        const locationHeading = [...doc.querySelectorAll(".section-head h2")].find(el=>clean(el.textContent).includes("Find Sanctuario"));
        const mapSection = locationHeading?.closest("section");
        mapSection?.querySelectorAll(".locations .location").forEach((el,i)=>add("Farm Info", "Location detail " + (i+1), "Text", el.textContent));
        if (about[1]) {
            addText("Farm Info", "Farm Process", about[1], {Heading:"h2", Description:"p"});
            about[1].querySelectorAll(".location").forEach((el,i)=>add("Farm Info", "Process step " + (i+1), "Text", el.textContent));
        }
        each(".products .product", (el,i)=>{
            const name = txt(el,"h3") || "Product " + (i+1);
            const item = name + " (card " + (i+1) + ")";
            addText("Farm Products", item, el, {Name:"h3", Category:".tag", Description:".product-body p"});
            add("Farm Products", item, "Image", el.querySelector(".product-image img")?.getAttribute("src") || "");
            for (const [attr,label] of [["data-price","Price"],["data-quantity","Quantity"],["data-stock","Stock"],["data-category","Category code"]]) add("Farm Products",item,label,el.getAttribute(attr)||"");
        });
        addText("Engagement", "Partnership", doc.querySelector(".partner"), {Heading:"h2", Description:"p"});
        each(".partner-list > div",(el,i)=>add("Engagement", "Partnership item " + (i+1), "Text", el.textContent));
        each(".faq .faq-item",(el,i)=>addText("Engagement", "FAQ " + (i+1), el, {Question:".faq-q", Answer:".faq-a"}));
        addText("Contact", "Contact heading", doc.querySelector("[data-cms-contact-heading]"), {Kicker:".kicker", Heading:"h2"});
        each(".contact-card .contact-row",(el,i)=>add("Contact", "Contact detail " + (i+1), "Text", el.textContent));
        addText("Contact", "Message form", doc.querySelector(".form-card"), {Heading:"h2", Note:".note"});
        add("Footer", "Footer", "Connect", doc.querySelector(".footer-connect")?.textContent || "");
        add("Footer", "Footer", "Explore", doc.querySelector(".footer-explore")?.textContent || "");
        add("Footer", "Footer", "Logo", doc.querySelector(".footer-logo img")?.getAttribute("src") || "");
        add("Footer", "Footer", "Copyright", doc.querySelector(".copyright")?.textContent || "");
        return records;
    }

    function historyChanges(beforeHtml, afterHtml) {
        const before = historySnapshot(beforeHtml);
        const after = historySnapshot(afterHtml);
        const groups = new Map();
        for (const key of new Set([...before.keys(),...after.keys()])) {
            const oldEntry = before.get(key), newEntry = after.get(key);
            const oldValue = oldEntry?.value || "", newValue = newEntry?.value || "";
            if (oldValue === newValue) continue;
            const entry = newEntry || oldEntry;
            if (!groups.has(entry.group)) groups.set(entry.group, new Map());
            const items = groups.get(entry.group);
            if (!items.has(entry.item)) items.set(entry.item, []);
            items.get(entry.item).push({field:entry.field, before:oldValue, after:newValue});
        }
        return groups;
    }

    function historyRenderChanges(container, beforeHtml, afterHtml) {
        const groups = historyChanges(beforeHtml,afterHtml);
        const count = [...groups.values()].reduce((sum,items)=>sum+items.size,0);
        const filter = document.createElement("select");
        filter.className = "cms-history-filter";
        const option = document.createElement("option"); option.value=""; option.textContent="All sections"; filter.appendChild(option);
        for (const group of groups.keys()) {
            const opt=document.createElement("option");opt.value=group;opt.textContent=group;filter.appendChild(opt);
        }
        const summary=document.createElement("p");
        summary.textContent = beforeHtml ? count + " changed container(s) detected" : "First saved version — no earlier snapshot to compare.";
        container.append(summary);
        if (!count) { const p=document.createElement("p");p.textContent="No tracked content changes between these versions.";container.append(p);return; }
        container.append(filter);
        const list=document.createElement("div");list.className="cms-history-changes";container.append(list);
        const draw=()=>{
            list.replaceChildren();
            for (const [group,items] of groups) {
                if (filter.value && filter.value!==group) continue;
                for (const [name,fields] of items) {
                    const card=document.createElement("details");card.className="cms-history-change-card";
                    const title=document.createElement("summary");title.textContent=group+"  /  "+name+"  ·  "+fields.length+" field(s)";card.append(title);
                    for (const field of fields) {
                        const label=document.createElement("div");label.className="cms-history-field-label";label.textContent=field.field;card.append(label);
                        const pair=document.createElement("div");pair.className="cms-history-field-pair";
                        for (const [caption,value] of [["Before",field.before],["After",field.after]]) {
                            const cell=document.createElement("div");
                            const small=document.createElement("strong");small.textContent=caption;
                            const body=document.createElement("p");body.textContent=value||"(empty / not present)";
                            cell.append(small,body);pair.append(cell);
                        }
                        card.append(pair);
                    }
                    list.append(card);
                }
            }
        };
        filter.addEventListener("change",draw);draw();
    }

    async function showHistory() {
        historyInstallPolish();
        historyBackToList();
        historyOverlay.hidden = false;
        historyList.textContent = "Loading saved versions...";
        try {
            const result = await historyRequest("list");
            historyEntries = result.entries || [];
            historyList.innerHTML = "";
            if (!historyEntries.length) {
                historyList.textContent = "No saved versions yet. Save a draft or publish to create the first entry.";
                return;
            }
            historyEntries.forEach(entry => {
                const item = document.createElement("div");
                item.className = "cms-history-entry";
                const title = document.createElement("div");
                title.className = "cms-history-entry-title";
                title.textContent = (entry.type === "published" ? "Published website" : entry.type === "before_restore" ? "Backup before restore" : "Draft saved") + " · " + historyPrettyDate(entry.timestamp);
                const meta = document.createElement("div");
                meta.className = "cms-history-entry-meta";
                meta.textContent = "Saved by " + (entry.actor || "Admin");
                const view = document.createElement("button");
                view.type = "button";
                view.textContent = "View Changes";
                view.onclick = () => viewHistoryEntry(entry.id);
                item.append(title, meta, view);
                historyList.appendChild(item);
            });
        } catch (error) {
            historyList.textContent = error.message;
        }
    }

    async function viewHistoryEntry(id) {
        historyInstallPolish();
        historySelectedId = id;
        const requestSerial = ++historyRequestSerial;
        historyList.hidden = true;
        historyDetails.hidden = false;
        historyDetails.textContent = "Loading changes...";
        const selected = historyEntries.find(entry => entry.id === id);
        const makeDetailsHeader = () => {
            const bar = document.createElement("div");
            bar.className = "cms-history-details-header";
            const heading = document.createElement("h3");
            heading.textContent = selected ? "Changes · " + historyPrettyDate(selected.timestamp) : "Saved version changes";
            const back = document.createElement("button");
            back.type = "button";
            back.className = "cms-history-back";
            back.textContent = "Back to History";
            back.onclick = historyBackToList;
            bar.append(heading, back);
            return bar;
        };
        try {
            const result = await historyRequest("detail", null, id);
            if (historySelectedId !== id || requestSerial !== historyRequestSerial) return;
            historyDetails.replaceChildren();
            const note = document.createElement("p");
            note.textContent = "Explore changes by section and container. Expand a container to compare Before and After.";
            historyDetails.append(makeDetailsHeader(), note);
            historyRenderChanges(historyDetails, result.previous_html || "", result.html || "");
            const restore = document.createElement("button");
            restore.type = "button";
            restore.className = "cms-history-restore";
            restore.textContent = "Restore as Draft";
            restore.onclick = async () => {
                if (!confirm("Restore this version as your current DRAFT? The current draft will be backed up. The public website will NOT change until you Publish.")) return;
                try {
                    restore.disabled = true;
                    await historyRequest("restore", { id });
                    alert("Version restored as draft. The editor will reload. Publish separately when ready.");
                    location.reload();
                } catch (error) {
                    alert(error.message);
                    restore.disabled = false;
                }
            };
            historyDetails.append(restore);
        } catch (error) {
            if (historySelectedId !== id || requestSerial !== historyRequestSerial) return;
            historyDetails.replaceChildren(makeDetailsHeader());
            const message = document.createElement("p");
            message.textContent = error.message;
            historyDetails.append(message);
        }
    }

    if (historyButton && historyOverlay) {
        historyButton.addEventListener("click", showHistory);
        $("cmsHistoryClose").addEventListener("click", () => {
            historyBackToList();
            historyOverlay.hidden = true;
        });
        historyOverlay.addEventListener("click", event => {
            if (event.target === historyOverlay) {
                historyBackToList();
                historyOverlay.hidden = true;
            }
        });
    }

    // ==========================================
    // CMS TOOLBAR CLEANUP FINAL
    // INITIALIZATION
    // ==========================================

    if ($("publishAll")) {
        $("publishAll").innerHTML =
            UPLOAD + "<span>Publish</span>";
    }

    frame.addEventListener("load", () => {
        try {
            installedDoc = null;
            history = [];
            historyIndex = -1;

            installEditor();

        } catch (error) {
            notify(error.message, true);
        }
    });

    if (
        frame.contentDocument?.readyState ===
        "complete"
    ) {
        try {
            installEditor();

        } catch (error) {
            notify(error.message, true);
        }
    }

})();
