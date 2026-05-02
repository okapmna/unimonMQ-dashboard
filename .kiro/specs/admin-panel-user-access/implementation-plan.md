# Implementation Plan: Admin Panel, User Access Control & Device Sharing

This plan breaks down the implementation of the Admin Panel, Role-Based Access Control, Device Sharing, and Spike Detection Logging.

## Proposed Changes

### 1. Database Migrations
-   Update `user`, `device`, and `device_logs` tables.
-   Create `device_access_tokens`, `user_device_access`, and `admin_audit_log` tables.

### 2. Role-Based Access Control (RBAC)
-   Initialize roles (assign `admin` to the first user or a specific user).
-   Update login/session logic to include `role`.
-   Create a helper function for admin-only page protection.

### 3. Admin Panel
-   Create `src/admin/` directory.
-   Implement `src/admin/users.php` (User management table).
-   Implement `src/admin/devices.php` (Device management table).
-   Implement `src/admin/tokens.php` (Token management).

### 4. Device Sharing via Tokens
-   Implement token generation logic (Admin).
-   Implement token redemption logic (User).
-   Update `src/dashboard.php` to include "Redeem Token" modal.

### 5. Dashboard & Access Logic Updates
-   Update `src/dashboard.php` to fetch and display shared devices.
-   Update device dashboard pages (Incubator/Smartlamp) to check for shared access.
-   Implement UI restrictions for "viewer" access.

### 6. MQTT Worker: Spike Detection
-   Update worker database config and queries.
-   Implement change-detection logic in `mqtt-worker/src/handlers/incubator.js`.
-   Ensure existing 5-minute aggregation remains functional.

---

## Detailed Steps

### Step 1: Database Setup
1.  Run SQL migration to add columns and new tables.
2.  Seed the `admin` role for at least one user.

### Step 2: RBAC & Navigation
1.  Modify `src/index.php` (login) to store `role` in `$_SESSION['role']`.
2.  Modify `src/components/header.php` to show "Admin Panel" link if `$_SESSION['role'] == 'admin'`.
3.  Create `src/admin/auth_check.php` to redirect non-admins.

### Step 3: Admin Panel - Users
1.  Create `src/admin/users.php`.
2.  Implement table with sorting, search, and pagination.
3.  Implement "Edit Role" and "Delete User" actions.

### Step 4: Admin Panel - Devices & Tokens
1.  Create `src/admin/devices.php`.
2.  Implement table with device details and owner info.
3.  Implement "Generate Token" modal and logic.
4.  Implement "View Tokens" modal to list and revoke tokens.

### Step 5: Token Redemption
1.  Update `src/dashboard.php` with a "Redeem Token" button and modal.
2.  Create `src/actions/redeem_token.php` to process token submissions.
3.  Implement logic to create `user_device_access` records.

### Step 6: Unified Dashboard
1.  Refactor `src/dashboard.php` query to use a `UNION` or `JOIN` to fetch both owned and shared devices.
2.  Add "OWNER" and "SHARED" badges to device cards.
3.  Disable "Edit" and "Delete" buttons for shared devices.

### Step 7: Device Dashboard Access
1.  Update `src/iot-dashboard/incubator32/incubator_dashboard.php` to check `user_device_access`.
2.  Hide "Target" controls and "Edit" options if the user has `viewer` access.

### Step 8: MQTT Worker Spike Detection
1.  Modify `mqtt-worker/src/handlers/incubator.js` to compare data with `last_logged_values`.
2.  Implement `UPDATE device SET last_logged_values = ...` when a spike is detected.
3.  Insert `change_event` log into `device_logs`.

---

## Verification Plan

### Automated Tests (Scripts/Unit)
-   **Token Redemption Test:** A script to simulate token generation and redemption, checking database states.
-   **RBAC Test:** A script to attempt accessing admin URLs with a regular user session.

### Manual Verification
1.  **Login as Admin:** Verify "Admin Panel" link appears.
2.  **User Management:** Change a user's role and verify it persists.
3.  **Device Sharing:**
    -   Generate a token for a device.
    -   Login as another user and redeem the token.
    -   Verify the device appears on the second user's dashboard with a "SHARED" badge.
4.  **Access Restrictions:**
    -   As a "viewer", open the shared device dashboard.
    -   Verify target controls are hidden/disabled.
    -   Verify MQTT messages are not sent when trying to change targets (if controls weren't hidden).
5.  **Spike Detection:**
    -   Publish a temperature change of +2.0 to a device topic.
    -   Verify a `change_event` entry appears in `device_logs`.
    -   Verify 5-minute aggregation still works correctly.
