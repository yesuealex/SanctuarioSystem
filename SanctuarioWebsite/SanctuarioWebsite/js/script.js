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

    // Hero slideshow controls.
    const slides = [...document.querySelectorAll(".slide")];
    const dots = [...document.querySelectorAll(".dot")];
    let slideIndex = 0;
    let slideTimer;

    if (slides.length) {
        const showSlide = nextIndex => {
            slideIndex = (nextIndex + slides.length) % slides.length;
            slides.forEach((slide, index) => slide.classList.toggle("active", index === slideIndex));
            dots.forEach((dot, index) => {
                dot.classList.toggle("active", index === slideIndex);
                dot.setAttribute("aria-pressed", String(index === slideIndex));
            });
        };
        const startTimer = () => {
            clearInterval(slideTimer);
            if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
                slideTimer = setInterval(() => showSlide(slideIndex + 1), 5000);
            }
        };
        document.querySelector(".next")?.addEventListener("click", () => { showSlide(slideIndex + 1); startTimer(); });
        document.querySelector(".prev")?.addEventListener("click", () => { showSlide(slideIndex - 1); startTimer(); });
        dots.forEach((dot, index) => dot.addEventListener("click", () => { showSlide(index); startTimer(); }));
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
                toast.textContent = `Thank you, ${name}! Your message is ready to be connected to the farm's inquiry system.`;
                toast.classList.add("show");
                setTimeout(() => toast.classList.remove("show"), 4000);
            } else {
                window.alert(`Thank you, ${name}! This demo form is not connected to a database or email service yet.`);
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
