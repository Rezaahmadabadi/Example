// Sidebar Toggle for Mobile
const menuToggle = document.getElementById('menuToggle');
const closeSidebar = document.getElementById('closeSidebar');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');

// Open Sidebar
if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.add('active');
        overlay.classList.add('active');
    });
}

// Close Sidebar
if (closeSidebar) {
    closeSidebar.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });
}

// Close Sidebar when clicking overlay
if (overlay) {
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });
}

// Handle window resize
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        // Close sidebar on desktop view
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    }, 250);
});

// Search functionality
const searchInput = document.querySelector('.search-box input');
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        console.log('جستجو برای:', searchTerm);
        // Add your search logic here
    });
}

// Menu items click handler
const menuItems = document.querySelectorAll('.sidebar-nav a');
if (menuItems.length > 0) {
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            // Close sidebar on mobile after clicking
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });
    });
}

// Active Menu Item
const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
sidebarLinks.forEach(link => {
    if (link.href === window.location.href) {
        link.parentElement.classList.add('active');
    }
});

// ========== توابع مدیریت فاکتورها ==========

// مشاهده فاکتور
window.viewInvoice = function(invoiceId) {
    console.log('Viewing invoice:', invoiceId);
    
    // لود اطلاعات فاکتور از طریق AJAX
    fetch(`get-invoice-details.php?id=${invoiceId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('فاکتور یافت نشد');
            }
            return response.text();
        })
        .then(data => {
            document.getElementById('invoiceDetails').innerHTML = data;
            document.getElementById('viewInvoiceModal').classList.add('active');
            overlay.classList.add('active');
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('invoiceDetails').innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #ff6b6b;">
                    <h4>خطا در بارگذاری فاکتور</h4>
                    <p>${error.message}</p>
                    <p>لطفاً صفحه را رفرش کنید و دوباره تلاش کنید.</p>
                </div>
            `;
            document.getElementById('viewInvoiceModal').classList.add('active');
            overlay.classList.add('active');
        });
};

// ارجاع فاکتور از لیست
window.referInvoice = function(invoiceId) {
    console.log('Referring invoice:', invoiceId);
    document.getElementById('refer_invoice_id').value = invoiceId;
    document.getElementById('referInvoiceModal').classList.add('active');
    overlay.classList.add('active');
};

// ارجاع فاکتور از صفحه مشاهده
window.referInvoiceFromView = function(invoiceId) {
    console.log('Referring invoice from view - Invoice ID:', invoiceId);
    
    // ابتدا مودال مشاهده رو ببند
    const viewModal = document.getElementById('viewInvoiceModal');
    if (viewModal) {
        viewModal.classList.remove('active');
        overlay.classList.remove('active');
    }
    
    // سپس مودال ارجاع رو باز کن
    const referModal = document.getElementById('referInvoiceModal');
    const referInput = document.getElementById('refer_invoice_id');
    
    if (referInput && referModal) {
        referInput.value = invoiceId;
        referModal.classList.add('active');
        overlay.classList.add('active');
        console.log('Refer modal opened successfully');
    } else {
        console.error('Refer modal or input not found');
    }
};

// پیش‌نمایش فایل فاکتور - نسخه بهبود یافته
window.previewInvoiceFile = function(filePath, isAdditional = false) {
    if (!filePath) return;
    
    console.log('Previewing file:', filePath);
    const fileUrl = `uploads/invoices/${filePath}`;
    const fileExtension = filePath.split('.').pop().toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(fileExtension);
    const isPdf = fileExtension === 'pdf';
    const isOffice = ['doc', 'docx', 'xls', 'xlsx'].includes(fileExtension);
    
    const previewContent = document.getElementById('filePreviewContent');
    const downloadLink = document.getElementById('downloadFile');
    
    downloadLink.href = fileUrl;
    downloadLink.download = filePath;
    
    if (isImage) {
        // پیش‌نمایش عکس
        previewContent.innerHTML = `<img src="${fileUrl}" style="max-width: 100%; max-height: 70vh; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">`;
    } else if (isPdf) {
        // پیش‌نمایش PDF با iframe
        previewContent.innerHTML = `
            <iframe src="${fileUrl}" 
                    style="width: 100%; height: 70vh; border: none; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);" 
                    frameborder="0"></iframe>
            <div style="margin-top: 1rem; text-align: center; color: rgba(255,255,255,0.7);">
                اگر PDF نمایش داده نمی‌شود، از دکمه دانلود استفاده کنید
            </div>
        `;
    } else if (isOffice) {
        // برای فایل‌های آفیس - پیش‌نمایش با Google Docs Viewer
        const encodedUrl = encodeURIComponent(window.location.origin + '/' + fileUrl);
        previewContent.innerHTML = `
            <iframe src="https://docs.google.com/gview?url=${encodedUrl}&embedded=true" 
                    style="width: 100%; height: 70vh; border: none; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);" 
                    frameborder="0"></iframe>
            <div style="margin-top: 1rem; text-align: center; color: rgba(255,255,255,0.7);">
                در حال بارگذاری پیش‌نمایش... اگر نمایش داده نمی‌شود، از دکمه دانلود استفاده کنید
            </div>
        `;
    } else {
        // برای سایر فایل‌ها - نمایش آیکون و لینک دانلود
        let fileIcon = '📄';
        let fileType = 'فایل';
        
        if (['zip', 'rar'].includes(fileExtension)) {
            fileIcon = '📦';
            fileType = 'فایل فشرده';
        }
        
        previewContent.innerHTML = `
            <div style="text-align: center; padding: 3rem;">
                <div style="font-size: 80px; margin-bottom: 1rem;">${fileIcon}</div>
                <div style="font-size: 1.5rem; color: #fff; margin-bottom: 0.5rem;">
                    ${isAdditional ? 'پیوست فاکتور' : 'فایل فاکتور'}
                </div>
                <div style="color: rgba(255,255,255,0.9); margin-bottom: 0.5rem;">${fileType}</div>
                <div style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">${filePath}</div>
                <div style="margin-top: 2rem; padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 10px;">
                    <p style="color: rgba(255,255,255,0.8); margin: 0;">
                        این نوع فایل قابل پیش‌نمایش در مرورگر نیست. لطفاً برای مشاهده آن را دانلود کنید.
                    </p>
                </div>
            </div>
        `;
    }
    
    document.getElementById('filePreviewModal').classList.add('active');
    overlay.classList.add('active');
};

// پرینت فایل
window.printFile = function() {
    // فقط محتوای مودال پیش‌نمایش رو چاپ کن
    const previewContent = document.getElementById('filePreviewContent').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
            <head>
                <title>پرینت فایل</title>
                <style>
                    body { 
                        margin: 0; 
                        padding: 20px; 
                        text-align: center;
                        font-family: 'Vazirmatn', Tahoma, Arial;
                    }
                    img { 
                        max-width: 100%; 
                        height: auto;
                    }
                </style>
            </head>
            <body>
                ${previewContent}
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// بستن مودال
window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        overlay.classList.remove('active');
    }
};

// بستن مودال با کلیک خارج از آن
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
        overlay.classList.remove('active');
    }
});

// تایید قبل از حذف
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('a[onclick*="confirm"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('آیا از این عمل اطمینان دارید؟')) {
                e.preventDefault();
            }
        });
    });
});

// توابع جدید برای حذف و ویرایش
window.deleteInvoice = function(invoiceId, invoiceNumber) {
    if (confirm(`آیا از حذف فاکتور شماره ${invoiceNumber} اطمینان دارید؟\nاین عمل غیرقابل بازگشت است!`)) {
        window.location.href = `invoice-management.php?delete_invoice=${invoiceId}`;
    }
};

window.editInvoice = function(invoiceId) {
    alert(`ویرایش فاکتور ${invoiceId}\nاین قابلیت در نسخه بعدی اضافه خواهد شد.`);
    // در آینده اینجا مودال ویرایش رو باز می‌کنیم
};

// فرمت کردن مبلغ به صورت زنده
window.formatAmountLive = function(input) {
    // حذف همه کاراکترهای غیرعددی
    let value = input.value.replace(/[^\d]/g, '');
    
    if (value) {
        // ذخیره مقدار عددی
        input.dataset.numericValue = value;
        
        // فرمت کردن و نمایش با جداکننده هزارگان
        input.value = parseInt(value).toLocaleString('en-US'); 
    } else {
        input.dataset.numericValue = '';
        input.value = '';
    }
};

// پیش‌نمایش فایل
window.previewFile = function(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
        const fileType = file.type;
        const fileName = file.name;
        
        let previewHTML = '';
        
        if (fileType.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewHTML = `
                    <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px; border: 1px solid #4a9eff;">
                        <img src="${e.target.result}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                        <div>
                            <div style="font-weight: bold; color: #fff;">${fileName}</div>
                            <div style="font-size: 12px; color: rgba(255,255,255,0.7);">${fileType} - ${fileSize} MB</div>
                        </div>
                    </div>
                `;
                preview.innerHTML = previewHTML;
            };
            reader.readAsDataURL(file);
        } else {
            // برای فایل‌های غیر عکس
            const fileIcon = getFileIcon(fileType, fileName);
            previewHTML = `
                <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px; border: 1px solid #4a9eff;">
                    <div style="font-size: 24px;">${fileIcon}</div>
                    <div>
                        <div style="font-weight: bold; color: #fff;">${fileName}</div>
                        <div style="font-size: 12px; color: rgba(255,255,255,0.7);">${fileType || 'ناشناخته'} - ${fileSize} MB</div>
                    </div>
                </div>
            `;
            preview.innerHTML = previewHTML;
        }
    } else {
        preview.innerHTML = '';
    }
};

// آیکون فایل بر اساس نوع و نام فایل
window.getFileIcon = function(fileType, fileName) {
    const extension = fileName.split('.').pop().toLowerCase();
    
    if (fileType.includes('pdf') || extension === 'pdf') return '📕';
    if (fileType.includes('word') || fileType.includes('document') || extension === 'doc' || extension === 'docx') return '📝';
    if (fileType.includes('excel') || fileType.includes('spreadsheet') || extension === 'xls' || extension === 'xlsx') return '📊';
    if (fileType.includes('zip') || fileType.includes('rar') || extension === 'zip' || extension === 'rar') return '📦';
    if (fileType.includes('image') || ['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(extension)) return '🖼️';
    return '📄';
};

// اعتبارسنجی فرم
document.addEventListener('DOMContentLoaded', function() {
    const invoiceForm = document.getElementById('invoiceForm');
    if (invoiceForm) {
        invoiceForm.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = '';
            
            // بررسی فیلد مبلغ
            const amountInput = document.getElementById('amount');
            const numericValue = amountInput.value.replace(/[^\d]/g, '');
            
            if (!numericValue || numericValue === '0') {
                isValid = false;
                errorMessage = 'لطفا مبلغ فاکتور را وارد کنید';
                amountInput.focus();
            }
            
            // بررسی فایل فاکتور
            const invoiceFile = document.getElementById('invoice_file');
            if (!invoiceFile || !invoiceFile.files[0]) {
                if (isValid) {
                    isValid = false;
                    errorMessage = 'لطفا فایل فاکتور را انتخاب کنید';
                }
            } else if (invoiceFile.files[0].size > 5 * 1024 * 1024) {
                if (isValid) {
                    isValid = false;
                    errorMessage = 'حجم فایل فاکتور نباید بیشتر از 5 مگابایت باشد';
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
            }
        });
    }
    
    // اعتبارسنجی فرم ارجاع
    const referForm = document.getElementById('referForm');
    if (referForm) {
        referForm.addEventListener('submit', function(e) {
            const toUserId = document.getElementById('to_user_id').value;
            const description = document.getElementById('refer_description').value;
            
            if (!toUserId || !description.trim()) {
                e.preventDefault();
                alert('لطفا تمام فیلدهای فرم ارجاع را پر کنید');
            }
        });
    }
});

// Format price function
window.formatPrice = function(price) {
    return new Intl.NumberFormat('fa-IR').format(price) + ' ریال';
};

// Initialize the dashboard
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎉 سیستم پیگیری فاکتور با موفقیت بارگذاری شد');
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function(e) {
            // Add custom tooltip logic here if needed
        });
    });
    
    // Add loading animation
    const cards = document.querySelectorAll('.stat-card, .glass-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});