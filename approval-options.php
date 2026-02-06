<?php
// approval-options.php
return [
    'categories' => [
        'financial' => [
            'title' => '📋 گزینه‌های مالی',
            'options' => [
                ['id' => 'amount_correct', 'text' => 'مبلغ فاکتور صحیح است', 'mandatory' => true],
                ['id' => 'tax_calculated', 'text' => 'کسر مالیات محاسبه شده', 'mandatory' => false],
                ['id' => 'discount_applied', 'text' => 'تخفیف‌ها اعمال شده', 'mandatory' => false],
                ['id' => 'contract_match', 'text' => 'مبلغ با قرارداد مطابقت دارد', 'mandatory' => true],
                ['id' => 'payment_approved', 'text' => 'برای پرداخت تأیید می‌شود', 'mandatory' => true],
                ['id' => 'calculation_correct', 'text' => 'محاسبات ریالی صحیح است', 'mandatory' => true],
                ['id' => 'exchange_valid', 'text' => 'نرخ ارز معتبر است', 'mandatory' => false],
                ['id' => 'taxes_correct', 'text' => 'عوارض و مالیات صحیح است', 'mandatory' => false],
            ]
        ],
        'inventory' => [
            'title' => '📦 گزینه‌های انبار/کالا',
            'options' => [
                ['id' => 'goods_received', 'text' => 'کالا/خدمت دریافت شد', 'mandatory' => true],
                ['id' => 'specs_match', 'text' => 'مشخصات فنی مطابقت دارد', 'mandatory' => true],
                ['id' => 'quantity_correct', 'text' => 'تعداد و مقدار صحیح است', 'mandatory' => true],
                ['id' => 'quality_control', 'text' => 'کنترل کیفیت انجام شد', 'mandatory' => false],
                ['id' => 'goods_healthy', 'text' => 'کالا سالم تحویل گرفته شد', 'mandatory' => false],
                ['id' => 'expiry_valid', 'text' => 'تاریخ انقضا معتبر است', 'mandatory' => false],
                ['id' => 'serial_correct', 'text' => 'سریال/کد کالا صحیح است', 'mandatory' => false],
            ]
        ],
        'documents' => [
            'title' => '📄 گزینه‌های مدارک',
            'options' => [
                ['id' => 'documents_complete', 'text' => 'مدارک کامل است', 'mandatory' => true],
                ['id' => 'signature_correct', 'text' => 'مهر و امضاء صحیح است', 'mandatory' => true],
                ['id' => 'dates_valid', 'text' => 'تاریخ‌ها معتبر هستند', 'mandatory' => true],
                ['id' => 'invoice_official', 'text' => 'فاکتور رسمی است', 'mandatory' => true],
                ['id' => 'barcode_valid', 'text' => 'بارکد/شناسه فاکتور معتبر است', 'mandatory' => false],
                ['id' => 'attachments_complete', 'text' => 'پیوست‌ها کامل است', 'mandatory' => false],
            ]
        ],
        'company' => [
            'title' => '🏢 گزینه‌های اطلاعات شرکت',
            'options' => [
                ['id' => 'economic_code', 'text' => 'کد اقتصادی معتبر است', 'mandatory' => true],
                ['id' => 'national_id', 'text' => 'شناسه ملی صحیح است', 'mandatory' => true],
                ['id' => 'address_complete', 'text' => 'نشانی شرکت کامل است', 'mandatory' => false],
                ['id' => 'registration_valid', 'text' => 'شماره ثبت معتبر است', 'mandatory' => false],
                ['id' => 'seller_correct', 'text' => 'اطلاعات فروشنده صحیح است', 'mandatory' => true],
            ]
        ],
        'process' => [
            'title' => '🔄 گزینه‌های فرآیند',
            'options' => [
                ['id' => 'purchase_principles', 'text' => 'با رعایت اصول خرید تهیه شده', 'mandatory' => true],
                ['id' => 'supervisor_approved', 'text' => 'تأیید سرپرست بخش گرفته شده', 'mandatory' => true],
                ['id' => 'system_registered', 'text' => 'در سیستم ثبت شده', 'mandatory' => true],
                ['id' => 'trackable', 'text' => 'قابلیت پیگیری دارد', 'mandatory' => false],
                ['id' => 'delivery_deadline', 'text' => 'مهلت تحویل رعایت شده', 'mandatory' => false],
                ['id' => 'company_policy', 'text' => 'مطابق با سیاست‌های شرکت است', 'mandatory' => true],
            ]
        ],
        'budget' => [
            'title' => '💰 گزینه‌های بودجه',
            'options' => [
                ['id' => 'budget_match', 'text' => 'مطابق با بودجه است', 'mandatory' => true],
                ['id' => 'budget_code', 'text' => 'کد بودجه صحیح است', 'mandatory' => true],
                ['id' => 'special_budget', 'text' => 'از محل بودجه اختصاصی است', 'mandatory' => false],
                ['id' => 'budget_balance', 'text' => 'مانده بودجه کافی است', 'mandatory' => true],
            ]
        ],
        'general' => [
            'title' => '✅ گزینه‌های عمومی',
            'options' => [
                ['id' => 'review_done', 'text' => 'بررسی کلی انجام شد', 'mandatory' => true],
                ['id' => 'no_discrepancy', 'text' => 'مغایرتی مشاهده نشد', 'mandatory' => false],
                ['id' => 'process_approved', 'text' => 'برای ادامه فرآیند تأیید می‌شود', 'mandatory' => true],
                ['id' => 'no_review_needed', 'text' => 'نیاز به بررسی مجدد ندارد', 'mandatory' => false],
            ]
        ]
    ]
];