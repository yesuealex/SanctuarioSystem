document.addEventListener("DOMContentLoaded", () => {
    const menu = document.querySelector(".menu");
    const nav = document.querySelector(".navlinks");
    const navLinks = [...document.querySelectorAll('.navlinks a[href^="#"]')];

    if (menu && nav) {
        menu.addEventListener("click", () => {
            const isOpen = nav.classList.toggle("open");
            menu.setAttribute("aria-expanded", String(isOpen));
            menu.setAttribute("aria-label", isOpen ? "Close navigation menu" : "Open navigation menu");
        });

        nav.querySelectorAll("a").forEach(link => {
            link.addEventListener("click", () => {
                nav.classList.remove("open");
                menu.setAttribute("aria-expanded", "false");
                menu.setAttribute("aria-label", "Open navigation menu");
            });
        });
    }

    // Keep nav targets accurate and update the active link as the user scrolls.
    const sections = navLinks
        .map(link => document.querySelector(link.getAttribute("href")))
        .filter(Boolean);

    navLinks.forEach(link => {
        link.addEventListener("click", event => {
            const target = document.querySelector(link.getAttribute("href"));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "start" });
            history.replaceState(null, "", link.getAttribute("href"));
            navLinks.forEach(item => item.classList.toggle("active", item === link));
        });
    });

    if ("IntersectionObserver" in window && sections.length) {
        const observer = new IntersectionObserver(entries => {
            const visible = entries
                .filter(entry => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (!visible) return;
            navLinks.forEach(link => {
                const active = link.getAttribute("href") === `#${visible.target.id}`;
                link.classList.toggle("active", active);
            });
        }, { rootMargin: "-105px 0px -58% 0px", threshold: [0, 0.1, 0.25, 0.5] });
        sections.forEach(section => observer.observe(section));
    }

    // The arrows stop at the first and last slides; autoplay continues looping.
    const slides = [...document.querySelectorAll(".slide")];
    const dots = [...document.querySelectorAll(".dot")];
    const previousArrow = document.querySelector(".arrow.prev");
    const nextArrow = document.querySelector(".arrow.next");
    let slideIndex = 0;
    let slideTimer;

    if (slides.length) {
        const showSlide = (nextIndex, loop = false) => {
            slideIndex = loop
                ? (nextIndex + slides.length) % slides.length
                : Math.max(0, Math.min(slides.length - 1, nextIndex));
            slides.forEach((slide, index) => slide.classList.toggle("active", index === slideIndex));
            dots.forEach((dot, index) => {
                dot.classList.toggle("active", index === slideIndex);
                dot.setAttribute("aria-pressed", String(index === slideIndex));
            });
            if (previousArrow) {
                previousArrow.disabled = slideIndex === 0;
                previousArrow.setAttribute("aria-label", "Previous slide");
            }
            if (nextArrow) {
                nextArrow.disabled = slideIndex === slides.length - 1;
                nextArrow.setAttribute("aria-label", "Next slide");
            }
        };
        const startTimer = () => {
            clearInterval(slideTimer);
            if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
                slideTimer = setInterval(() => showSlide(slideIndex + 1, true), 5000);
            }
        };
        previousArrow?.addEventListener("click", () => {
            if (slideIndex > 0) showSlide(slideIndex - 1);
            startTimer();
        });
        nextArrow?.addEventListener("click", () => {
            if (slideIndex < slides.length - 1) showSlide(slideIndex + 1);
            startTimer();
        });
        dots.forEach((dot, index) => dot.addEventListener("click", () => {
            showSlide(index);
            startTimer();
        }));
        showSlide(0);
        startTimer();
    }

    // Product filters: show only the selected category to keep the page compact.
    const filterButtons = [...document.querySelectorAll(".filter-btn")];
    const productCards = [...document.querySelectorAll(".product[data-category]")];
    filterButtons.forEach(button => {
        button.addEventListener("click", () => {
            const filter = button.dataset.filter;
            filterButtons.forEach(item => {
                const active = item === button;
                item.classList.toggle("active", active);
                item.setAttribute("aria-pressed", String(active));
            });
            productCards.forEach(card => {
                card.hidden = filter !== "all" && card.dataset.category !== filter;
            });
        });
    });

    // Product detail modal is informational only; values are editable on each product card.
    const productModal = document.querySelector("#productModal");
    if (productModal) {
        const closeModal = () => {
            productModal.classList.remove("open");
            productModal.setAttribute("aria-hidden", "true");
            document.body.classList.remove("modal-open");
        };
        document.querySelectorAll(".product-details").forEach(button => {
            button.addEventListener("click", () => {
                const card = button.closest(".product");
                const image = card.querySelector("img");
                const name = card.querySelector("h3")?.textContent.trim() || "Product Details";
                const category = card.querySelector(".tag")?.textContent.trim() || "Product";
                const description = card.querySelector("p")?.textContent.trim() || "";
                const stock = card.dataset.stock === "out" ? "🔴 No Stock" : "🟢 In Stock";
                document.querySelector("#productModalTitle").textContent = name;
                document.querySelector("#productModalCategory").textContent = category;
                document.querySelector("#productModalDescription").textContent = description;
                document.querySelector("#productModalStock").textContent = stock;
                document.querySelector("#productModalQuantity").textContent = card.dataset.quantity || "0 pcs";
                document.querySelector("#productModalPrice").textContent = card.dataset.price || "₱0";
                const modalImage = document.querySelector("#productModalImage");
                modalImage.src = image?.getAttribute("src") || "";
                modalImage.alt = name;
                modalImage.style.visibility = image && image.complete && image.naturalWidth ? "visible" : "hidden";
                modalImage.onerror = () => { modalImage.style.visibility = "hidden"; };
                productModal.classList.add("open");
                productModal.setAttribute("aria-hidden", "false");
                document.body.classList.add("modal-open");
                productModal.querySelector(".product-modal-close")?.focus();
            });
        });
        
productModal.querySelector(".product-modal-close")?.addEventListener("click", closeModal);

productModal.querySelector(".product-modal-close-action")?.addEventListener("click", () => {
    closeModal();

    const contactSection = document.querySelector("#contact");

    if (contactSection) {
        contactSection.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });

        history.replaceState(null, "", "#contact");
    }
});

        productModal.addEventListener("click", event => { if (event.target === productModal) closeModal(); });
        document.addEventListener("keydown", event => { if (event.key === "Escape" && productModal.classList.contains("open")) closeModal(); });
    }

    // Accessible accordion: one FAQ opens at a time.
    document.querySelectorAll(".faq-q").forEach(button => {
        button.setAttribute("aria-expanded", "false");
        button.addEventListener("click", () => {
            const item = button.closest(".faq-item");
            const shouldOpen = !item.classList.contains("open");
            document.querySelectorAll(".faq-item").forEach(other => {
                other.classList.remove("open");
                const otherButton = other.querySelector(".faq-q");
                if (otherButton) {
                    otherButton.setAttribute("aria-expanded", "false");
                    const sign = otherButton.querySelector("span");
                    if (sign) sign.textContent = "+";
                }
            });
            if (shouldOpen) {
                item.classList.add("open");
                button.setAttribute("aria-expanded", "true");
                const sign = button.querySelector("span");
                if (sign) sign.textContent = "−";
            }
        });
    });

    // Frontend-only contact confirmation; form submission is not stored or emailed yet.
    const form = document.querySelector("#contactForm");
    const toast = document.querySelector("#toast");
    if (form) {
        form.addEventListener("submit", event => {
            event.preventDefault();
            const name = form.querySelector('[name="name"]')?.value.trim() || "there";
            if (toast) {
                toast.textContent = `Thank you, ${name}! Your message is ready to be connected to the farm's inquiry .`;
                toast.classList.add("show");
                setTimeout(() => toast.classList.remove("show"), 4000);
            } else {
                window.alert(`Thank you, ${name}! This message connected to the Sanctuario farm.`);
            }
            form.reset();
        });
    }

    // Floating back-to-top control.
    const backToTopButton = document.querySelector(".back-to-top");
    if (backToTopButton) {
        const updateVisibility = () => backToTopButton.classList.toggle("is-visible", window.scrollY > 280);
        window.addEventListener("scroll", updateVisibility, { passive: true });
        updateVisibility();
        backToTopButton.addEventListener("click", () => {
            window.scrollTo({ top: 0, behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
            history.replaceState(null, "", "#home");
        });
    }

    document.querySelectorAll(".year").forEach(element => element.textContent = new Date().getFullYear());
});