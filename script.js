// ==================== SMOOTH SCROLL ==================== 
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault(); // مانع التسجيل العادي (مانوقفش النافبار)
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth', // ننزلوو بسلاسة
                block: 'start' // نبداو من فوق العنصر
            });
        }
    });
});

// ==================== SCROLL ANIMATIONS ==================== 
const observerOptions = {
    threshold: 0.1, // 10% من العنصر يظهر باش نشعلو الأنيميشن
    rootMargin: '0px 0px -50px 0px' // نتحكمو فالمساحة خارج الشاشة
};

const observer = new IntersectionObserver(function (entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) { // إل العنصر دخل الشاشة
            entry.target.style.animation = 'fadeIn 0.8s ease-out';
            observer.unobserve(entry.target); // ما نراقبوش تاني
        }
    });
}, observerOptions);

// نراقب كل الكارتات والخدمات
document.querySelectorAll('.about-card, .service-card').forEach(el => {
    observer.observe(el);
});

// ==================== FORM VALIDATION ==================== 
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email); // نفحصو إميل صحيح
}

function validatePhone(phone) {
    const phoneRegex = /^[0-9]{10,}$/;
    return phoneRegex.test(phone.replace(/\D/g, '')); // 10 أرقام على الأقل
}

function validateForm(formData) {
    const errors = {}; // فاضي باش نخزنو الأخطاء

    // فحص الاسم
    if (!formData.fullName || formData.fullName.trim().length < 3) {
        errors.fullName = 'الاسم الكامل يجب أن يكون 3 أحرف على الأقل';
    }

    // فحص الإيميل
    if (!validateEmail(formData.email)) {
        errors.email = 'البريد الإلكتروني غير صحيح';
    }

    // فحص الهاتف
    if (!validatePhone(formData.phone)) {
        errors.phone = 'رقم الهاتف غير صحيح';
    }

    // فحص كلمة المرور
    if (!formData.password || formData.password.length < 6) {
        errors.password = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    }

    return errors; // نرجعو الأخطاء
}

// ==================== MESSAGE SYSTEM ==================== 
function showMessage(message, type = 'info') {
    // نخلقو عنصر للرسالة
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type}`; // كلاس على حسب النوع
    messageDiv.textContent = message;

    // نحطو الرسالة فالبداية
    const container = document.querySelector('.form-container') || document.body;
    container.insertBefore(messageDiv, container.firstChild);

    // نزيلو الرسالة بعد 5 ثواني
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

// ==================== FILE UPLOAD VALIDATION ==================== 
function validateFile(file, maxSize = 5242880) { // 5MB default
    // الأنواع المسموح بها
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    // فحص النوع
    if (!allowedTypes.includes(file.type)) {
        return { valid: false, error: 'صيغة الملف غير مدعومة. استخدم PDF، صور، أو وثائق Word' };
    }

    // فحص الحجم
    if (file.size > maxSize) {
        return { valid: false, error: 'حجم الملف كبير جداً (الحد الأقصى 5 ميجابايت)' };
    }

    return { valid: true }; // كلشي كو
}

// ==================== FORM SUBMISSION ==================== 
function setupFormSubmission(formId, actionUrl) {
    const form = document.getElementById(formId);
    if (!form) return; // إذا ماكاينش الفورم، طلع

    form.addEventListener('submit', async (e) => {
        e.preventDefault(); // مانرسلش بالطريقة العادية

        const formData = new FormData(form); // نعمل FormData من الفورم

        try {
            // نرسلو البيانات للسيرفر
            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json(); // ناخد الجواب

            if (result.success) {
                showMessage(result.message, 'success'); // رسالة نجاح
                form.reset(); // نفضيو الفورم

                // ننتظرو شوية وندرجو لصفحة أخرى
                setTimeout(() => {
                    window.location.href = result.redirect || 'index.html';
                }, 2000);
            } else {
                showMessage(result.message, 'danger'); // رسالة خطأ
            }
        } catch (error) {
            showMessage('حدث خطأ في الاتصال. حاول مرة أخرى.', 'danger');
            console.error('Error:', error); // نطبعو الخطأ فكونسول
        }
    });
}

// ==================== NAVBAR SCROLL EFFECT ==================== 
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }
});

// ==================== COUNTER ANIMATION ==================== 
function animateCounter(element, target, duration = 2000) {
    let start = 0;
    const suffix = element.dataset.suffix || '';
    const increment = target / (duration / 16);
    const timer = setInterval(() => {
        start += increment;
        if (start >= target) {
            element.textContent = `${target}${suffix}`;
            clearInterval(timer);
        } else {
            element.textContent = `${Math.floor(start)}${suffix}`;
        }
    }, 16);
}

// Initialize counters when in viewport
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const statNumber = entry.target;
            const target = parseInt(statNumber.getAttribute('data-count'));
            if (target && !statNumber.classList.contains('counted')) {
                animateCounter(statNumber, target);
                statNumber.classList.add('counted');
            }
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('.stat-number').forEach(el => {
    counterObserver.observe(el);
});

// ==================== PREMIUM SCROLL ANIMATIONS ==================== 
const premiumObserverOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const premiumObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animation = 'fadeInUp 0.8s ease-out';
            entry.target.style.opacity = '1';
            premiumObserver.unobserve(entry.target);
        }
    });
}, premiumObserverOptions);

// Observe all cards and sections
document.querySelectorAll('.profession-card, .craftsman-card, .step-card, .trust-item, .review-card').forEach(el => {
    el.style.opacity = '0';
    premiumObserver.observe(el);
});

// Observe stat cards for counter animation
document.querySelectorAll('.stat-card .stat-number').forEach(el => {
    counterObserver.observe(el);
});

// ==================== HAMBURGER MENU ==================== 
const hamburgerMenu = document.getElementById('hamburgerMenu');
const navMenu = document.getElementById('navMenu');

if (hamburgerMenu && navMenu) {
    hamburgerMenu.addEventListener('click', () => {
        hamburgerMenu.classList.toggle('active');
        navMenu.classList.toggle('active');
    });
}

// ==================== BACK TO TOP BUTTON ==================== 
const backToTopBtn = document.getElementById('backToTop');
if (backToTopBtn) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            backToTopBtn.style.display = 'flex';
        } else {
            backToTopBtn.style.display = 'none';
        }
    });

    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// ==================== TRANSLATION SYSTEM ====================
function initLanguageSystem() {
    let currentLang = localStorage.getItem('hirafi_lang') || 'ar';
    injectLanguageSwitcher(currentLang);
    applyLanguage(currentLang);
}

function injectLanguageSwitcher(currentLang) {
    if (document.getElementById('langSwitcher')) return;

    const switcher = document.createElement('div');
    switcher.id = 'langSwitcher';
    switcher.className = 'lang-switcher-wrap';

    switcher.innerHTML = `
        <div class="lang-toggle">
            <button class="lang-btn ${currentLang === 'ar' ? 'active' : ''}" onclick="changeLanguage('ar')">
                <span class="lang-flag">🇲🇦</span> AR
            </button>
            <span class="lang-divider"></span>
            <button class="lang-btn ${currentLang === 'fr' ? 'active' : ''}" onclick="changeLanguage('fr')">
                <span class="lang-flag">🇫🇷</span> FR
            </button>
        </div>
    `;

    const navContainer = document.querySelector('.nav-container') || document.querySelector('.navbar-container');
    const headerBar = document.querySelector('.header-bar');
    const sidebar = document.querySelector('.sidebar') || document.querySelector('.side');

    if (headerBar) {
        headerBar.appendChild(switcher);
    } else if (navContainer) {
        const hamburger = document.getElementById('hamburgerMenu');
        if (hamburger) {
            navContainer.insertBefore(switcher, hamburger);
        } else {
            navContainer.appendChild(switcher);
        }
    } else if (sidebar) {
        sidebar.appendChild(switcher);
    } else {
        document.body.appendChild(switcher);
    }
}

window.changeLanguage = function (lang) {
    localStorage.setItem('hirafi_lang', lang);
    location.reload();
};

function applyLanguage(lang) {
    if (!window.translations || !window.translations[lang]) {
        console.warn('Global translations dictionary not loaded yet or invalid language choice.');
        return;
    }

    document.documentElement.dir = (lang === 'fr') ? 'ltr' : 'rtl';
    document.documentElement.lang = lang;

    if (lang === 'fr') {
        document.body.classList.add('lang-ltr');
        document.body.classList.remove('lang-rtl');
    } else {
        document.body.classList.add('lang-rtl');
        document.body.classList.remove('lang-ltr');
    }

    const elements = document.querySelectorAll('[data-i18n]');
    elements.forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (translations[lang] && translations[lang][key]) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.placeholder = translations[lang][key];
            } else if (el.getAttribute('data-i18n-html') === 'true') {
                el.innerHTML = translations[lang][key];
            } else {
                const icon = el.querySelector('i');
                if (icon) {
                    el.innerHTML = '';
                    el.appendChild(icon);
                    el.appendChild(document.createTextNode(' ' + translations[lang][key]));
                } else {
                    el.textContent = translations[lang][key];
                }
            }
        }
    });

    // Translate placeholder select options if present
    document.querySelectorAll('select option[data-i18n]').forEach(opt => {
        const key = opt.getAttribute('data-i18n');
        if (translations[lang] && translations[lang][key]) {
            opt.textContent = translations[lang][key];
        }
    });
}

// ==================== INITIALIZE ==================== 
document.addEventListener('DOMContentLoaded', () => {
    console.log('الموقع جاهز للاستخدام');

    // Initialize localization before animation trigger
    initLanguageSystem();

    // Add animation classes to hero elements
    document.querySelectorAll('.hero-title, .hero-subtitle, .hero-stats, .hero-actions, .hero-image').forEach((el, index) => {
        el.style.opacity = '0';
        setTimeout(() => {
            el.style.animation = `fadeInUp 0.8s ease-out ${index * 0.1}s both`;
        }, 100);
    });

    // Stagger animations for cards
    document.querySelectorAll('.profession-card, .craftsman-card, .step-card, .trust-item, .review-card').forEach((el, index) => {
        el.style.opacity = '0';
        setTimeout(() => {
            el.style.animation = `fadeInUp 0.6s ease-out ${index * 0.05}s both`;
        }, 100);
    });

    // Gallery hover behavior: on mouseenter set active to hovered, reset to first on mouseleave
    const gallery = document.querySelector('.profession-gallery');
    const galleryItems = document.querySelectorAll('.profession-gallery .gallery-item');
    if (gallery && galleryItems.length) {
        const first = galleryItems[0];
        // ensure first is active by default
        galleryItems.forEach((it, idx) => {
            if (idx === 0) it.classList.add('active');
            else it.classList.remove('active');
        });

        galleryItems.forEach((item) => {
            item.addEventListener('mouseenter', () => {
                galleryItems.forEach((s) => s.classList.remove('active'));
                item.classList.add('active');
            });
        });

        gallery.addEventListener('mouseleave', () => {
            galleryItems.forEach((s) => s.classList.remove('active'));
            if (first) first.classList.add('active');
        });
    }

    const featuredSection = document.querySelector('.featured-craftsmen');
    const craftsmanCards = featuredSection ? Array.from(featuredSection.querySelectorAll('.craftsman-card')) : [];
    let hoveredCraftsmanIndex = null;

    const setActiveCraftsman = (index) => {
        hoveredCraftsmanIndex = index;
        craftsmanCards.forEach((card, idx) => {
            if (idx === index) {
                card.classList.add('active');
                card.classList.remove('inactive');
            } else {
                card.classList.remove('active');
                card.classList.add('inactive');
            }
        });
    };

    const resetCraftsmanHover = () => {
        hoveredCraftsmanIndex = null;
        craftsmanCards.forEach((card) => {
            card.classList.remove('active', 'inactive');
        });
    };

    craftsmanCards.forEach((card, index) => {
        card.addEventListener('mouseenter', () => {
            if (hoveredCraftsmanIndex !== index) {
                setActiveCraftsman(index);
            }
        });
    });

    if (featuredSection) {
        featuredSection.addEventListener('mouseleave', resetCraftsmanHover);
    }

    // ===== CTA single-image flip setup =====
    function initCtaFlip() {
        const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const flip = document.querySelector('.flip-single[data-back]');
        if (!flip) return;

        const img = flip.querySelector('img.flip-front');
        if (!img) return;

        const backSrc = flip.getAttribute('data-back');
        const originalSrc = img.getAttribute('src');
        const duration = 700; // ms (matches CSS)
        const half = Math.round(duration / 2);
        let timeoutId = null;

        const clear = () => { if (timeoutId) { clearTimeout(timeoutId); timeoutId = null; } };

        const doFlipToBack = () => {
            clear();
            if (prefersReduced) {
                img.src = backSrc;
                return;
            }
            flip.classList.add('flipping');
            timeoutId = setTimeout(() => {
                img.src = backSrc;
            }, half);
        };

        const doFlipToFront = () => {
            clear();
            if (prefersReduced) {
                img.src = originalSrc;
                return;
            }
            // rotate back visually
            flip.classList.remove('flipping');
            timeoutId = setTimeout(() => {
                img.src = originalSrc;
            }, half);
        };

        // Mouse and keyboard
        flip.addEventListener('mouseenter', doFlipToBack, { passive: true });
        flip.addEventListener('mouseleave', doFlipToFront, { passive: true });
        flip.addEventListener('focusin', doFlipToBack);
        flip.addEventListener('focusout', doFlipToFront);

        // Make it programmatically focusable for keyboard users
        if (!flip.hasAttribute('tabindex')) flip.setAttribute('tabindex', '0');
    }

    initCtaFlip();
});

// Populate localized cities in any Select element
window.populateCitiesSelect = function(selectId, manualInputId, selectedValue) {
    const select = document.getElementById(selectId);
    if (!select) return;

    const lang = localStorage.getItem('hirafi_lang') || 'ar';
    const cities = window.moroccanCities || [];

    // Save previous placeholder option if any
    const firstOpt = select.querySelector('option[value=""]');
    const placeholderText = firstOpt ? firstOpt.textContent : (lang === 'fr' ? 'Choisir la ville' : 'اختر المدينة');

    select.innerHTML = '';
    
    // Add placeholder option
    const placeholderOpt = document.createElement('option');
    placeholderOpt.value = "";
    placeholderOpt.textContent = placeholderText;
    select.appendChild(placeholderOpt);

    let isCustom = selectedValue && selectedValue !== "";

    // Add cities
    cities.forEach(c => {
        const name = lang === 'fr' ? c.fr : c.ar;
        const opt = document.createElement('option');
        opt.value = c.ar; // store Arabic in DB for consistency
        opt.textContent = name;
        if (selectedValue && (selectedValue === c.ar || selectedValue === c.fr)) {
            opt.selected = true;
            isCustom = false;
        }
        select.appendChild(opt);
    });

    // Add custom option
    const customOpt = document.createElement('option');
    customOpt.value = "أخرى";
    customOpt.textContent = lang === 'fr' ? "📍 Autre (Saisir manuellement)" : "📍 أخرى (إدخال يدوي)";
    if (selectedValue === "أخرى" || isCustom) {
        customOpt.selected = true;
    }
    select.appendChild(customOpt);

    // Setup event listener to toggle manual text input
    const manualInput = document.getElementById(manualInputId);
    if (manualInput) {
        const toggleInput = () => {
            if (select.value === "أخرى") {
                manualInput.style.display = "block";
                manualInput.required = true;
                if (isCustom && select.value === "أخرى") {
                    manualInput.value = selectedValue;
                }
            } else {
                manualInput.style.display = "none";
                manualInput.required = false;
                manualInput.value = "";
            }
        };

        select.addEventListener('change', toggleInput);
        toggleInput(); // run once on load
    }
};

// Populate localized artisan crafts in any Select element
window.populateCraftsSelect = function(selectId, selectedValue) {
    const select = document.getElementById(selectId);
    if (!select) return;

    const lang = localStorage.getItem('hirafi_lang') || 'ar';
    const crafts = window.artisanCrafts || [];

    const firstOpt = select.querySelector('option[value=""]');
    const placeholderText = firstOpt ? firstOpt.textContent : (lang === 'fr' ? 'Choisir le métier' : 'اختر المهنة');

    select.innerHTML = '';
    
    const placeholderOpt = document.createElement('option');
    placeholderOpt.value = "";
    placeholderOpt.textContent = placeholderText;
    select.appendChild(placeholderOpt);

    crafts.forEach(c => {
        const name = lang === 'fr' ? c.fr : c.ar;
        const opt = document.createElement('option');
        opt.value = c.ar; // store Arabic for consistency
        opt.textContent = name;
        if (selectedValue && (selectedValue === c.ar || selectedValue === c.fr)) {
            opt.selected = true;
        }
        select.appendChild(opt);
    });

    // Always add "Other" option
    const otherOpt = document.createElement('option');
    otherOpt.value = "أخرى";
    otherOpt.textContent = lang === 'fr' ? "Autre métier" : "مهنة أخرى";
    if (selectedValue === "أخرى") {
        otherOpt.selected = true;
    }
    select.appendChild(otherOpt);
};