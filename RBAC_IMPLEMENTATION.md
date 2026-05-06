# Role-Based Access Control (RBAC) Implementation
## نظام التحكم بالصلاحيات حسب الأدوار

### Overview
The Library Management System now implements a complete Role-Based Access Control system with three user types:

1. **User (مستخدم)** - Regular library users who can:
   - View available books
   - Borrow books
   - Return books

2. **Staff (موظف)** - Library staff who can:
   - Do everything a user can do
   - Add new books to the system
   - Delete books from the system
   - Manage user accounts (view, delete)

3. **Manager (مدير)** - Library managers who can:
   - Do everything a staff member can do
   - Register new staff members
   - Delete staff members
   - Full administrative access

---

## Implementation Details

### Backend Changes (BACKEND/user_staff.php)

#### 1. Session-Based Role Detection
```php
function getSessionRole(): ?string
{
    if (isset($_SESSION['user_id'])) {
        return 'user';
    }
    if (isset($_SESSION['staff_id'])) {
        return (isset($_SESSION['is_manager']) && $_SESSION['is_manager']) ? 'manager' : 'staff';
    }
    return null;
}
```

#### 2. Permission Verification Functions
- `requireStaff()`: Returns true if current user is staff or manager
- `requireManager()`: Returns true if current user is manager only

#### 3. Updated Login Functions
- `userLogin()`: Now stores session data and returns role information
- `staffLogin()`: Determines if staff is manager based on position = 'Library Manager'

#### 4. Protected Operations
- `registerStaff()`: Protected with `requireManager()` check
- `deleteStaff()`: Protected with `requireManager()` check
- `addBook()`: Protected with `requireStaff()` check
- `deleteBook()`: Protected with `requireStaff()` check

#### 5. New Endpoints
- `session_check`: Returns current role and user info
- `logout`: Destroys session and logs out user
- `user_login` / `staff_login`: Login endpoints with role detection

### Frontend Changes (FRONT END/)

#### 1. HTML Structure (index.html)
- Added `data-require-role` attributes to major sections:
  - `#panel-edit-book`: `data-require-role="staff"`
  - `#panel-borrow`: `data-require-role="user"`
  - `#panel-users`: `data-require-role="staff"`
  - `#panel-staff`: `data-require-role="manager"`

- Added login modal:
  - Two tabs: "مستخدم" (User) and "موظف" (Staff)
  - Login form for users
  - Login form for staff
  - Message display area

#### 2. CSS Styling (styles.css)
```css
/* Modal overlay */
.modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

/* Login form tabs */
.login-tabs {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}

.login-tab-btn.active {
    background: var(--primary);
    color: #fff;
}

/* Login forms visibility */
.login-form {
    display: none !important;
}

.login-form.active {
    display: flex !important;
}
```

#### 3. JavaScript Functionality (script.js)

##### Session Management
```javascript
async function checkSession() {
    // Called on page load
    // Fetches current session info and updates UI
    // Loads books only after role is determined
}
```

##### UI Control Based on Role
```javascript
function updateUIForRole() {
    // Shows/hides sections based on state.currentRole
    // Handles login modal visibility
    // Updates logout button
}
```

##### Login Handlers
```javascript
// User Login
document.getElementById('form-user-login').addEventListener('submit', async (event) => {
    // Sends 'user_login' action to backend
    // Updates state with role and user info
    // Calls updateUIForRole() to show appropriate sections
});

// Staff Login
document.getElementById('form-staff-login').addEventListener('submit', async (event) => {
    // Sends 'staff_login' action to backend
    // Determines if staff is manager from is_manager flag
    // Calls updateUIForRole() to show appropriate sections
});
```

##### Login Tab Switching
```javascript
document.querySelectorAll('.login-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        // Switch active tab
        // Show corresponding login form
    });
});
```

##### Logout Functionality
```javascript
async function addLogoutButton() {
    // Creates logout button in hero section
    // Shows current username
    // Clears session and updates UI on logout
}
```

---

## Database Setup

### Test User Accounts
Created via `BACKEND/setup_test_users.php`:

| Type | Username | Password | Role |
|------|----------|----------|------|
| User | testuser | test123 | User |
| Staff | staff | staff123 | Staff |
| Manager | manager | manager123 | Manager |

---

## Security Features

### 1. Server-Side Validation
- All write operations validate user role before execution
- Invalid operations return error messages in Arabic
- Unauthorized access responses include "غير مصرح" (Not Authorized)

### 2. Session-Based Authentication
- Uses PHP sessions to track logged-in users
- Session data includes role-identifying information
- Session destroyed on logout

### 3. Password Security
- Passwords hashed with bcrypt (via `hashPassword()`)
- Legacy MD5 passwords automatically upgraded on login
- Uses `verifyPassword()` for secure comparison

### 4. Defense in Depth
- Client-side UI filtering (user experience)
- Server-side permission checks (security)
- Both mechanisms ensure proper access control

---

## UI Visibility Rules

### When User is NOT Logged In
- Login modal displayed (covers entire screen)
- All content panels hidden
- Two login tabs available: User and Staff

### When User is Logged In as "User"
- Login modal hidden
- Only `[data-require-role="user"]` sections shown
- Can access: Books browsing, Borrow/Return operations

### When Staff Member is Logged In as "Staff"
- Login modal hidden
- `[data-require-role="user"]` and `[data-require-role="staff"]` sections shown
- Can access: Everything user can do + Book management + User management
- Manager section hidden

### When Staff Member is Logged In as "Manager"
- Login modal hidden
- ALL sections shown (all `[data-require-role]` elements)
- Full administrative access

---

## File Changes Summary

### Modified Files
1. **BACKEND/user_staff.php**
   - Added `session_start()` at top
   - Added role-checking helper functions
   - Updated login functions to store session data
   - Added `session_check` and `logout` endpoints
   - Protected staff registration and deletion with manager check

2. **BACKEND/library.php**
   - Protected `addBook()` with staff role requirement
   - Protected `deleteBook()` with staff role requirement

3. **FRONT END/index.html**
   - Added `data-require-role` attributes to sections
   - Added login modal with user/staff tabs
   - Removed inline styles from login forms

4. **FRONT END/styles.css**
   - Added `.modal` and `.modal-content` styles
   - Added `.login-tabs` and `.login-tab-btn` styles
   - Added `.login-form` visibility control with !important

5. **FRONT END/script.js**
   - Updated `state` object with `currentRole` and `currentUser`
   - Added `checkSession()` function
   - Updated `updateUIForRole()` function
   - Added login tab switching handlers
   - Added user and staff login form handlers
   - Added `addLogoutButton()` function

### New Files
1. **BACKEND/setup_test_users.php** - Creates test user accounts
2. **BACKEND/check_users.php** - Displays user and staff counts

---

## Testing the System

### Test Case 1: User Login
1. Open application (login modal appears)
2. Keep "مستخدم" tab selected
3. Username: `testuser`, Password: `test123`
4. Click login
5. Expected: Borrow/Return section visible, Edit Books hidden, Staff hidden

### Test Case 2: Staff Login
1. Open application (login modal appears)
2. Click "موظف" tab
3. Username: `staff`, Password: `staff123`
4. Click login
5. Expected: Borrow/Return + Edit Books + Users visible, Staff hidden

### Test Case 3: Manager Login
1. Open application (login modal appears)
2. Click "موظف" tab
3. Username: `manager`, Password: `manager123`
4. Click login
5. Expected: All sections visible (Borrow/Return, Edit Books, Users, Staff)

### Test Case 4: Logout
1. Login as any user
2. Click "تسجيل الخروج" button in top-right
3. Expected: Session cleared, login modal reappears

### Test Case 5: Unauthorized Action (Server-Side Check)
1. Try to add a book without logging in as staff
2. Expected: "غير مصرح" error message from server

---

## Error Handling

### Frontend Messages
- Login failure: "فشل تسجيل الدخول" (Login failed)
- Server error: "خطأ في الاتصال بالخادم" (Server connection error)
- Success: "تم تسجيل الدخول بنجاح" (Successfully logged in)

### Backend Responses
```javascript
{
    "success": false,
    "message": "غير مصرح"  // Not authorized
}
```

---

## Session Variables Reference

### User Session
```php
$_SESSION['user_id']      // User's ID
$_SESSION['username']     // Username
$_SESSION['user_status']  // Active/Inactive
```

### Staff Session
```php
$_SESSION['staff_id']         // Staff ID
$_SESSION['username']         // Username
$_SESSION['member_position']  // Position title
$_SESSION['staff_status']     // Active/Inactive
$_SESSION['is_manager']       // Boolean (true if position = 'Library Manager')
```

---

## API Endpoints

### User Portal (user_staff.php)
- `POST` with `action: 'user_login'` - Login as user
- `POST` with `action: 'staff_login'` - Login as staff
- `POST` with `action: 'logout'` - Logout current user
- `POST` with `action: 'session_check'` - Get current session role

### Library Operations (library.php)
- All book operations now check `requireStaff()` on write
- All staff operations check `requireManager()` on write

---

## Future Enhancements

1. Add fine/penalty viewing based on role
2. Add borrowing history with role-based visibility
3. Add audit logging for manager actions
4. Add role-specific statistics dashboard
5. Add password change functionality
6. Add session timeout handling

---

## Development Notes

- All error messages support UTF-8 Arabic text
- RTL (Right-to-Left) layout maintained throughout
- CSS uses CSS variables for theming
- Session-based approach ensures role persists across page refreshes
- Login modal prevents interaction with content until authenticated

