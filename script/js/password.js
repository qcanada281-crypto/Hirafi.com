document.addEventListener('DOMContentLoaded', () => {
    // =================== ⭐ الجزء 1: إنشاء الأنماط ⭐ ===================
    // ننشئ عنصر style ونضيفه لـ head لعرض أيقونة إظهار/إخفاء كلمة المرور
    const style = document.createElement('style');
    style.textContent = `
    /*  تنسيقات حاوية كلمة المرور */
    .pw-wrapper {
        position: relative;       /* علشان نتحكم في موضع الزر داخلها */
        display: block;           /* نجعلها تأخذ عرض كامل */
        width: 100%;              /* عرض 100% من العنصر الأم */
    }
    
    /*  تنسيق حقل الإدخال */
    .pw-wrapper input {
        width: 100%;              /* عرض كامل */
        padding-inline-start: 14px;  /* مسافة من اليسار */
        padding-inline-end: 44px;    /* مسافة من اليمين علشان نخلي مكان للزر */
    }
    
    /*  زر التبديل */
    .pw-toggle {
        position: absolute;        /* نضعه داخل الحاوية */
        top: 50%;                  /* في المنتصف عمودياً */
        transform: translateY(-50%); /* نركزه تماماً */
        inset-inline-end: 12px;   /* مسافة من الطرف اليمين (RTL) */
        background: transparent;   /* خلفية شفافة */
        border: none;              /* بدون إطار */
        cursor: pointer;           /* مؤشر يد عند التمرير */
        padding: 6px;              /* مسافة داخلية */
        border-radius: 6px;        /* زوايا مدورة */
        color: #7a3f2b;            /* لون بني */
    }
    
    /*  عند التركيز على الزر */
    .pw-toggle:focus {
        outline: none;              /* نحذف الخط الخارجي الافتراضي */
        box-shadow: 0 8px 20px rgba(122,63,43,0.10); /* ظل خفيف */
    }
    
    /*  تنسيق الأيقونة */
    .pw-toggle svg {
        width: 20px;    /* عرض الأيقونة */
        height: 20px;   /* ارتفاع الأيقونة */
        display: block; /* تنسيق كتلة */
    }
    
    /*  عندما تكون كلمة المرور ظاهرة */
    .pw-toggle.active {
        color: #3b2f2f; /* لون داكن أكثر */
    }
    `;
    document.head.appendChild(style);

    // =================== ⭐ الجزء 2: تعريف الأيقونات ⭐ ===================
    //  أيقونة العين المفتوحة (لإظهار كلمة المرور)
    const eye = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2.5 12s3.5-6.5 9.5-6.5S21.5 12 21.5 12s-3.5 6.5-9.5 6.5S2.5 12 2.5 12z"></path>
        <circle cx="12" cy="12" r="3"></circle>
    </svg>`;
    
    //  أيقونة العين المغلقة (لإخفاء كلمة المرور)
    const eyeSlash = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19.5C6 19.5 1.73 16.39 .01 12c.65-1.65 1.62-3.13 2.8-4.34"></path>
        <path d="M3 3l18 18"></path>
    </svg>`;

    // ===================  الجزء 3: البحث عن جميع حقول كلمة المرور  ===================
    // نجمع كل حقول الإدخال من نوع password في مصفوفة
    const pwInputs = Array.from(document.querySelectorAll('input[type="password"]'));
    
    //  نمر على كل حقل من حقول كلمة المرور
    pwInputs.forEach((input) => {
        // ⏭ نتخطى الحقول التي سبق وأن أضفنا لها الزر (لنتجنب التكرار)
        if (input.closest('.pw-wrapper')) return;

        // ===================  الجزء 4: إنشاء الحاوية  ===================
        //  ننشئ div جديد لتكون حاوية للحقل والزر
        const wrapper = document.createElement('div');
        wrapper.className = 'pw-wrapper'; // نضيف كلاس pw-wrapper

        //  نستبدل الحقل بالحاوية (ننقل الحقل داخل الحاوية)
        input.parentNode.insertBefore(wrapper, input); // نضع الحاوية مكان الحقل
        wrapper.appendChild(input); // نضع الحقل داخل الحاوية

        // =================== ⭐ الجزء 5: إنشاء زر التبديل ⭐ ===================
        //  ننشئ زر التبديل
        const btn = document.createElement('button');
        btn.type = 'button'; // نوع زر عادي (ليس submit)
        btn.className = 'pw-toggle'; // نضيف كلاس pw-toggle
        btn.setAttribute('aria-label', 'إظهار كلمة المرور'); // وصف للقارئات الشاشية
        btn.innerHTML = eye; // نضيف أيقونة العين المفتوحة

        //  نضيف الزر للحاوية
        wrapper.appendChild(btn);

        // =================== ⭐ الجزء 6: إضافة حدث النقر ⭐ ===================
        //  نضيف مستمع حدث للنقر على الزر
        btn.addEventListener('click', () => {
            //  نتحقق إذا كان النص ظاهراً حالياً
            const showing = input.type === 'text';
            
            //  نبدل نوع الحقل بين text و password
            input.type = showing ? 'password' : 'text';
            
            //  نبدل حالة الكلاس active
            btn.classList.toggle('active', !showing);
            
            //  نحدث خاصية aria-pressed للقارئات الشاشية
            btn.setAttribute('aria-pressed', String(!showing));
            
            // نحدث وصف الزر للقارئات الشاشية
            btn.setAttribute('aria-label', showing ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور');
            
            //  نبدل الأيقونة بين العين المفتوحة والمغلقة
            btn.innerHTML = showing ? eye : eyeSlash;
        });
    });
});