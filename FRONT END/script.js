// Use relative paths to reach backend from the FRONT END folder
const ENDPOINTS = {
    library: '../BACKEND/library.php',
    portal: '../BACKEND/user_staff.php'
};

const state = {
    books: [],
    availableBooks: [],
    borrowings: [],
    lastSync: null,
    currentRole: null,  // 'user', 'staff', 'manager', or null
    currentUser: null   // { user_id, username } or { staff_id, username, is_manager }
};

const panels = document.querySelectorAll('.panel');
const navButtons = document.querySelectorAll('.nav-btn');

navButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
        // Check if button requires a role and user has it
        const requiredRole = btn.dataset.requireRole;
        if (requiredRole && state.currentRole !== requiredRole && state.currentRole !== 'manager') {
            showToast('غير مصرح للدخول لهذا القسم', 'error');
            return;
        }
        switchPanel(btn.dataset.panel);
    });
});

function switchPanel(panelId) {
    navButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.panel === panelId));
    panels.forEach((panel) => panel.classList.toggle('active', panel.id === `panel-${panelId}`));
}

const todayDateEl = document.getElementById('today-date');
todayDateEl.textContent = new Intl.DateTimeFormat('ar-EG', {
    dateStyle: 'full'
}).format(new Date());

const lastSyncEl = document.getElementById('last-sync');
const toastContainer = document.getElementById('toast-container');

// Check session on page load
async function checkSession() {
    try {
        const result = await post(ENDPOINTS.portal, { action: 'session_check' });
        if (result.success && result.role) {
            state.currentRole = result.role;
            state.currentUser = result;
            updateUIForRole();
            loadBooks();
        } else {
            state.currentRole = null;
            state.currentUser = null;
            updateUIForRole();
        }
    } catch (error) {
        console.error('Session check failed:', error);
    }
}

// Show/hide UI elements based on user role
function updateUIForRole() {
    const role = state.currentRole;
    
    // Hide all role-restricted elements by default
    document.querySelectorAll('[data-require-role]').forEach(el => {
        el.style.display = 'none';
    });

    // Show appropriate elements based on role
    if (role === 'user') {
        // Users can only borrow/return
        document.querySelectorAll('[data-require-role="user"]').forEach(el => {
            // Nav buttons use flex, panels use block
            el.style.display = el.classList.contains('nav-btn') ? 'flex' : 'block';
        });
    } else if (role === 'staff') {
        // Staff can do user actions + manage books
        document.querySelectorAll('[data-require-role="user"], [data-require-role="staff"]').forEach(el => {
            el.style.display = el.classList.contains('nav-btn') ? 'flex' : 'block';
        });
    } else if (role === 'manager') {
        // Managers can do everything
        document.querySelectorAll('[data-require-role]').forEach(el => {
            el.style.display = el.classList.contains('nav-btn') ? 'flex' : 'block';
        });
    }
    
    // Show/hide login section
    const loginSection = document.getElementById('login-modal');
    if (loginSection) {
        loginSection.style.display = role ? 'none' : 'flex';
    }
    
    // Update logout button
    addLogoutButton();
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

async function post(endpoint, payload) {
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => formData.append(key, value));
    const response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'include' });
    const data = await response.json();
    if (!data) throw new Error('لم يتم استلام استجابة');
    return data;
}

function setAlert(id, message, type = 'info') {
    const el = document.getElementById(id);
    if (!el) return;
    if (!message) {
        el.classList.remove('show');
        el.textContent = '';
        return;
    }
    el.textContent = message;
    el.dataset.type = type;
    el.classList.add('show');
}

function updateStat(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function renderBooks(list) {
    const container = document.getElementById('books-list');
    if (!list || list.length === 0) {
        container.innerHTML = `<div class="empty-state">لا توجد كتب مطابقة للبحث</div>`;
        return;
    }
    container.innerHTML = list
        .map(
            (book) => `
        <article class="book-card">
            <img class="book-cover" 
                 src="${ENDPOINTS.library}?cover=${book.book_id}"
                 alt="${book.book_name}"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
            <div class="book-cover-placeholder" style="display:none;">
                📚
            </div>
            <div class="book-info">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="badge ${book.available_copies > 0 ? 'success' : 'warning'}">
                        ${book.available_copies > 0 ? 'متاح' : 'غير متاح'}
                        (${book.available_copies})
                    </div>
                    <span style="font-size: 0.8rem; background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px;">#${book.book_id}</span>
                </div>
                <h3>${book.book_name}</h3>
                <p class="meta">${book.author_name || 'مؤلف غير معروف'} • ${book.release_year || '—'}</p>
                <p class="description">${book.book_description || 'لا يوجد وصف مختصر للكتاب.'}</p>
            </div>
        </article>`
        )
        .join('');
}

async function loadBooks() {
    try {
        const allBooks = await post(ENDPOINTS.library, { action: 'books_get_all' });
        const available = await post(ENDPOINTS.library, { action: 'books_get_available' });

        state.books = allBooks?.data || [];
        state.availableBooks = available?.data || [];

        // حساب مجموع النسخ المتاحة والكلية
        const totalCopies = state.books.reduce((sum, book) => sum + (parseInt(book.available_copies) || 0), 0);
        const totalBooksCount = state.books.length;

        updateStat('stat-total-books', totalCopies);
        updateStat('stat-book-count', totalBooksCount);

        lastSyncEl.textContent = new Intl.DateTimeFormat('ar-EG', {
            timeStyle: 'short',
            dateStyle: 'medium'
        }).format(new Date());

        renderBooks(state.books);
    } catch (error) {
        showToast('تعذر تحميل الكتب، تحقق من الخادم.', 'error');
        console.error(error);
    }
}

const addBookFormEl = document.getElementById('form-add-book');
if (addBookFormEl) {
    addBookFormEl.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = Object.fromEntries(new FormData(event.target));
        try {
            const result = await post(ENDPOINTS.library, {
                action: 'books_add',
                ...formData
            });
            if (result.success) {
                setAlert('add-book-message', 'تم إضافة الكتاب بنجاح', 'success');
                showToast('تم إضافة الكتاب');
                event.target.reset();
                loadBooks();
            } else {
                setAlert('add-book-message', result.message, 'error');
            }
        } catch (error) {
            setAlert('add-book-message', 'حدث خطأ أثناء إضافة الكتاب', 'error');
        }
    });
}

document.getElementById('form-delete-book').addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = Object.fromEntries(new FormData(event.target));

    if (!confirm('هل أنت متأكد من حذف هذا الكتاب؟ لا يمكن التراجع عن هذا الإجراء.')) {
        return;
    }

    try {
        const result = await post(ENDPOINTS.library, {
            action: 'books_delete',
            book_id: formData.book_id
        });
        if (result.success) {
            setAlert('delete-book-message', 'تم حذف الكتاب بنجاح', 'success');
            showToast('تم حذف الكتاب');
            event.target.reset();
            loadBooks();
        } else {
            setAlert('delete-book-message', result.message, 'error');
        }
    } catch (error) {
        setAlert('delete-book-message', 'حدث خطأ أثناء حذف الكتاب', 'error');
    }
});

document.getElementById('action-borrow').addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = Object.fromEntries(new FormData(event.target));
    try {
        const result = await post(ENDPOINTS.library, {
            action: 'borrow_create',
            ...formData
        });
        if (result.success) {
            setAlert('borrow-message', `${result.message} - التسليم ${result.due_date}`, 'success');
            showToast('تم تسجيل الإعارة بنجاح');
            event.target.reset();
            loadBooks();
        } else {
            setAlert('borrow-message', result.message, 'error');
        }
    } catch (error) {
        setAlert('borrow-message', 'خطأ غير متوقع، حاول مجدداً', 'error');
    }
});

document.getElementById('action-return').addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = Object.fromEntries(new FormData(event.target));
    try {
        const result = await post(ENDPOINTS.library, {
            action: 'borrow_return',
            ...formData
        });
        if (result.success) {
            setAlert('return-message', result.message, 'success');
            showToast('تم تسجيل الإرجاع');
            event.target.reset();
            loadBooks();
        } else {
            setAlert('return-message', result.message, 'error');
        }
    } catch {
        setAlert('return-message', 'حدث خلل أثناء الإرجاع', 'error');
    }
});

document.getElementById('form-user').addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = Object.fromEntries(new FormData(event.target));
    try {
        const result = await post(ENDPOINTS.portal, {
            action: 'register_user',
            ...formData
        });
        if (result.success) {
            setAlert('user-message', result.message, 'success');
            showToast('تم تسجيل المستخدم');
            event.target.reset();
        } else {
            setAlert('user-message', result.message, 'error');
        }
    } catch {
        setAlert('user-message', 'لم يتم التسجيل، حاول مرة أخرى', 'error');
    }
});

document.getElementById('form-user-borrows').addEventListener('submit', async (event) => {
    event.preventDefault();
    const { user_id } = Object.fromEntries(new FormData(event.target));
    try {
        const result = await post(ENDPOINTS.library, {
            action: 'borrow_user_list',
            user_id
        });
        const list = document.getElementById('user-borrowings');
        const items = result?.data || [];
        if (items.length === 0) {
            list.innerHTML = '<div class="empty-state">لا توجد إعارات حالياً</div>';
            return;
        }
        list.innerHTML = items
            .map(
                (item) => `
            <div class="list-item">
                <h4>${item.book_name}</h4>
                <p>رمز العملية: ${item.process_code}</p>
                <p>الحالة: ${item.status}</p>
                <p>الاستحقاق: ${item.due_date}</p>
            </div>`
            )
            .join('');
    } catch {
        showToast('تعذر تحميل الإعارات', 'error');
    }
});

document.getElementById('form-delete-user').addEventListener('submit', async (event) => {
    event.preventDefault();
    const { user_id } = Object.fromEntries(new FormData(event.target));
    
    if (!confirm('هل أنت متأكد من حذف هذا المستخدم؟ لا يمكن التراجع عن هذا الإجراء.')) {
        return;
    }

    try {
        const result = await post(ENDPOINTS.portal, {
            action: 'delete_user',
            user_id
        });
        if (result.success) {
            setAlert('delete-user-message', result.message, 'success');
            showToast('تم حذف المستخدم بنجاح');
            event.target.reset();
        } else {
            setAlert('delete-user-message', result.message, 'error');
        }
    } catch (error) {
        setAlert('delete-user-message', 'حدث خطأ أثناء الحذف', 'error');
    }
});

document.getElementById('form-staff').addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.target));
    try {
        const result = await post(ENDPOINTS.portal, {
            action: 'register_staff',
            ...payload
        });
        if (result.success) {
            setAlert('staff-message', result.message, 'success');
            showToast('تمت إضافة الموظف');
            event.target.reset();
        } else {
            setAlert('staff-message', result.message, 'error');
        }
    } catch {
        setAlert('staff-message', 'تعذر إتمام الطلب', 'error');
    }
});

document.getElementById('form-delete-staff').addEventListener('submit', async (event) => {
    event.preventDefault();
    const { staff_id } = Object.fromEntries(new FormData(event.target));
    
    if (!confirm('هل أنت متأكد من حذف هذا الموظف؟ لا يمكن التراجع عن هذا الإجراء.')) {
        return;
    }

    try {
        const result = await post(ENDPOINTS.portal, {
            action: 'delete_staff',
            staff_id
        });
        if (result.success) {
            setAlert('delete-staff-message', result.message, 'success');
            showToast('تم حذف الموظف بنجاح');
            event.target.reset();
        } else {
            setAlert('delete-staff-message', result.message, 'error');
        }
    } catch (error) {
        setAlert('delete-staff-message', 'حدث خطأ أثناء الحذف', 'error');
    }
});

// Login Tab Switching
document.querySelectorAll('.login-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const mode = btn.dataset.mode;
        
        // Update active tab
        document.querySelectorAll('.login-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Show/hide login forms
        document.querySelectorAll('.login-form').forEach(form => form.classList.remove('active'));
        document.getElementById(`form-${mode}-login`).classList.add('active');
    });
});

// User Login Handler
document.getElementById('form-user-login').addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = Object.fromEntries(new FormData(event.target));
    
    try {
        const result = await post(ENDPOINTS.portal, {
            action: 'user_login',
            ...formData
        });
        
        if (result.success) {
            setAlert('login-message', 'تم تسجيل الدخول بنجاح', 'success');
            showToast('مرحباً ' + result.username);
            state.currentRole = result.role;
            state.currentUser = result;
            updateUIForRole();
            loadBooks();
            event.target.reset();
        } else {
            setAlert('login-message', result.message || 'فشل تسجيل الدخول', 'error');
        }
    } catch (error) {
        setAlert('login-message', 'خطأ في الاتصال بالخادم', 'error');
        console.error(error);
    }
});

// Staff Login Handler
document.getElementById('form-staff-login').addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = Object.fromEntries(new FormData(event.target));
    
    try {
        const result = await post(ENDPOINTS.portal, {
            action: 'staff_login',
            ...formData
        });
        
        if (result.success) {
            setAlert('login-message', 'تم تسجيل الدخول بنجاح', 'success');
            showToast('مرحباً ' + result.username);
            state.currentRole = result.role;
            state.currentUser = result;
            updateUIForRole();
            loadBooks();
            event.target.reset();
        } else {
            setAlert('login-message', result.message || 'فشل تسجيل الدخول', 'error');
        }
    } catch (error) {
        setAlert('login-message', 'خطأ في الاتصال بالخادم', 'error');
        console.error(error);
    }
});

// Logout Handler - Add logout button to sidebar or hero
// Create a logout button in the header if user is logged in
async function addLogoutButton() {
    const headerMeta = document.querySelector('.hero-meta');
    if (!headerMeta || headerMeta.querySelector('#logout-btn')) return;
    
    if (state.currentRole) {
        const logoutBtn = document.createElement('button');
        logoutBtn.id = 'logout-btn';
        logoutBtn.innerHTML = `🚪 تسجيل الخروج (${state.currentUser.username})`;
        logoutBtn.style.cssText = 'border: none; background: #f87171; color: white; padding: 10px 16px; border-radius: 12px; cursor: pointer; font-weight: 600;';
        
        logoutBtn.addEventListener('click', async () => {
            try {
                await post(ENDPOINTS.portal, { action: 'logout' });
                state.currentRole = null;
                state.currentUser = null;
                updateUIForRole();
                showToast('تم تسجيل الخروج بنجاح');
                logoutBtn.remove();
            } catch (error) {
                showToast('خطأ في تسجيل الخروج', 'error');
            }
        });
        
        headerMeta.appendChild(logoutBtn);
    }
}

// Initialize: check session and load UI
checkSession();

