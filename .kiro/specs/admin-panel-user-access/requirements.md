# Admin Panel, User Access Control & Device Sharing

## Introduction

This feature introduces an admin panel with Excel-like table views, a role-based access control system (admin/user), a device sharing mechanism via tokens, and an incubator-specific change-detection logging system. Currently, the UnimonMQ dashboard has a flat user model where every registered user can only see and manage their own devices (1 user -> many devices). This feature transforms the system into a multi-role, multi-user-per-device architecture where admins manage devices and users, and regular users access shared devices by pasting a token provided by an admin.

---

## Requirements

### 1. Admin Panel with Excel-like Table View

**As an** admin,
**I want** a dedicated admin panel page with Excel-like table views for users and devices,
**so that** I can efficiently manage all users and devices in the system in a structured, sortable, and searchable format.

**Acceptance Criteria:**

1. **When** the logged-in user has the admin role, **the system shall** display an "Admin Panel" navigation link in the main navigation bar.
2. **When** an admin navigates to the admin panel, **the system shall** display a tabbed interface with at minimum two tabs: "Users" and "Devices".
3. **When** the "Users" tab is active, **the system shall** display a table with columns: User ID, Username, Role (admin/user), Number of Devices, and Actions (Edit Role, Delete).
4. **When** the "Devices" tab is active, **the system shall** display a table with columns: Device ID, Device Name, Device Type, Broker URL, Owner (admin username), Assigned Users count, and Actions (Edit, Delete, Generate Token).
5. **The system shall** support sorting by clicking on column headers (ascending/descending toggle).
6. **The system shall** provide a search/filter input above each table to filter rows by keyword.
7. **The system shall** support pagination when the number of rows exceeds 25 per page.
8. **When** a non-admin user attempts to access the admin panel URL directly, **the system shall** redirect them to the dashboard with an "Access Denied" error message.

---

### 2. Role-Based Access Control (Admin / User)

**As an** admin,
**I want** to assign and manage user roles (admin or user),
**so that** I can control who has administrative privileges and who is a regular user.

**Acceptance Criteria:**

1. **The system shall** add a `role` column to the `user` table with possible values `admin` and `user`, defaulting to `user` on registration.
2. **When** a new user registers, **the system shall** assign the `user` role by default.
3. **When** an admin changes a user's role via the admin panel, **the system shall** update the role immediately and reflect the change on the next page load.
4. **The system shall** prevent the last remaining admin from being demoted to user role (to avoid a system with no admins).
5. **When** an admin edits a user's role, **the system shall** require a confirmation prompt before saving.
6. **The system shall** store the user role in the session upon login and check it for authorization on every admin-protected page.

---

### 3. Device Sharing via Token (Admin-Generated)

**As an** admin,
**I want** to generate a unique access token/code for each device I configure,
**so that** I can share devices with regular users by giving them the token, and they can access the device by simply pasting the token.

**Acceptance Criteria:**

1. **The system shall** create a new `device_access_tokens` table with columns: `token_id` (auto-increment PK), `device_id` (FK to device), `token_code` (unique string), `created_by` (user_id of the admin who created it), `max_uses` (nullable integer, number of users who can redeem this token, NULL = unlimited), `current_uses` (integer, default 0), `expires_at` (nullable datetime), `is_active` (boolean, default true), `created_at` (timestamp).
2. **When** an admin clicks "Generate Token" for a device in the admin panel, **the system shall** generate a unique, human-readable token code (e.g., 8-character alphanumeric like `INK-4F2A9B1C`) and insert it into `device_access_tokens`.
3. **When** a token is generated, **the system shall** display the token code to the admin with a copy-to-clipboard button.
4. **The system shall** allow the admin to optionally set an expiry date and a maximum use count when generating a token.
5. **When** a token's `current_uses` reaches `max_uses` (if set), **the system shall** automatically mark the token as inactive.
6. **When** a token's `expires_at` datetime has passed, **the system shall** automatically mark the token as inactive.
7. **The system shall** allow the admin to manually revoke/deactivate any active token from the admin panel.
8. **The system shall** list all tokens for a device in the admin panel with their status (active/inactive), use count, and expiry.

---

### 4. User Device Access via Token Redemption

**As a** regular user,
**I want** to paste a token code provided by an admin and instantly see the associated device(s) on my dashboard,
**so that** I can monitor and interact with devices that the admin has configured and shared with me without needing to manually set up MQTT broker details.

**Acceptance Criteria:**

1. **The system shall** create a new `user_device_access` junction table with columns: `id` (auto-increment PK), `user_id` (FK to user), `device_id` (FK to device), `access_type` (enum: 'owner', 'viewer'), `redeemed_via_token_id` (FK to device_access_tokens, nullable), `granted_at` (timestamp).
2. **When** a user is on their dashboard, **the system shall** display a "Redeem Token" button/link.
3. **When** a user clicks "Redeem Token", **the system shall** show an input field where the user can paste a token code.
4. **When** a user submits a valid, active token code, **the system shall**:
   - Create a record in `user_device_access` linking the user to the device with `access_type` = 'viewer'.
   - Increment `current_uses` on the token.
   - Display a success message and add the device to the user's dashboard.
5. **When** a user submits an invalid, expired, fully-used, or inactive token, **the system shall** display an appropriate error message (e.g., "Token not found", "Token has expired", "Token has reached its usage limit").
6. **When** a user already has access to a device (via token or ownership), **the system shall** not create a duplicate entry and shall inform the user they already have access.
7. **The system shall** display both owned and shared devices on the user's dashboard, with a visual distinction (e.g., badge or label: "Owned" vs "Shared").
8. **When** a shared device is displayed on a user's dashboard, **the system shall** allow the user to view and monitor the device but restrict editing of broker configuration (only the owner/admin can edit broker settings).

---

### 5. Many-to-Many User-Device Relationship

**As an** admin,
**I want** multiple users to be able to access the same device,
**so that** a single device (e.g., an incubator) can be monitored by several users simultaneously.

**Acceptance Criteria:**

1. **The system shall** support a many-to-many relationship between users and devices via the `user_device_access` junction table.
2. **When** the admin views a device in the admin panel, **the system shall** display a list of all users who have access to that device (both owner and viewers).
3. **The system shall** allow the admin to manually grant access to a user for a device without requiring a token (direct assignment from admin panel).
4. **The system shall** allow the admin to revoke a user's access to a device from the admin panel.
5. **When** a device is deleted by the admin, **the system shall** cascade-delete all related `user_device_access` and `device_access_tokens` records for that device.
6. **When** a user is deleted by the admin, **the system shall** cascade-delete all `user_device_access` records for that user (but not delete the devices themselves).

---

### 6. Incubator Change-Detection Logging

**As a** user monitoring an incubator device,
**I want** the system to log only significant changes (e.g., temperature spike of +1 or -1 degree),
**so that** I can see a meaningful history of notable events rather than repetitive identical readings, making it easier to spot anomalies and trends.

**Acceptance Criteria:**

1. **The system shall** add a `last_logged_values` JSON column to the `device` table (or a separate `device_last_state` table) to store the last logged sensor values for each incubator device (e.g., `{"temperature": 37.5, "humidity": 55}`).
2. **When** the MQTT background worker receives sensor data for an incubator device, **the system shall** compare the incoming values against `last_logged_values`.
3. **If** the temperature change is >= +1 or <= -1 from the last logged value, **the system shall** insert a change-event log entry into `device_logs` with a JSON payload that includes: `{"event": "change_detected", "sensor": "temperature", "previous_value": X, "new_value": Y, "delta": Z, "timestamp": "..."}`.
4. **If** the humidity change is >= +1 or <= -1 from the last logged value, **the system shall** insert a change-event log entry into `device_logs` with a similar payload for humidity.
5. **The system shall** update `last_logged_values` only when a change-event is logged (not on every sample).
6. **The existing 5-minute aggregation logging** shall continue to function as-is; the change-detection logging is an additional log type, not a replacement.
7. **When** the user views the incubator dashboard history, **the system shall** display change-event logs alongside (or in a separate section from) the aggregated 5-minute logs, with visual indicators (e.g., colored markers) for spike events.
8. **The system shall** distinguish change-event logs from aggregation logs in `device_logs` by adding a `log_type` column with values `aggregation` (existing 5-min summaries) and `change_event` (new spike detection logs). Existing rows default to `aggregation`.

---

### 7. Security & Edge Cases

**As a** system administrator,
**I want** the new features to be secure and handle edge cases properly,
**so that** the system remains reliable and protected from unauthorized access.

**Acceptance Criteria:**

1. **The system shall** validate that only admins can access, create, revoke, and manage tokens.
2. **The system shall** use prepared statements (parameterized queries) for all new database operations to prevent SQL injection (the current codebase has some raw query usage that should not be replicated).
3. **The system shall** validate token codes on redemption server-side (not just client-side).
4. **The system shall** prevent a user from redeeming a token for a device they already own.
5. **The system shall** log admin actions (role changes, token generation, access grants/revocations) in a simple `admin_audit_log` table for traceability.
6. **The system shall** handle the case where a device's owner (admin) is deleted by transferring ownership to another admin or requiring ownership reassignment before deletion.
7. **When** a token is redeemed, **the system shall** perform the operation within a database transaction to prevent race conditions (e.g., two users redeeming the last slot of a limited-use token simultaneously).

---

### 8. Dashboard Update for Shared Devices

**As a** user with shared device access,
**I want** my dashboard to show all devices I have access to (both owned and shared),
**so that** I have a unified view of all my monitored devices.

**Acceptance Criteria:**

1. **When** a user views their dashboard, **the system shall** query both owned devices (via `device.user_id`) and shared devices (via `user_device_access`) and display them together.
2. **The system shall** visually distinguish shared devices from owned devices (e.g., a "Shared" badge or different card border color).
3. **When** a user clicks on a shared incubator device, **the system shall** open the incubator dashboard with full monitoring capability (real-time data, charts, history).
4. **When** a user clicks on a shared incubator device, **the system shall** hide the device configuration edit option (broker URL, port, MQTT credentials) since those are admin-only settings.
5. **The system shall** allow a user to "remove" a shared device from their dashboard view without affecting other users' access to the same device (this removes the `user_device_access` entry for that user).
6. **When** a user's access to a device is revoked by an admin, **the system shall** immediately remove the device from the user's dashboard on next page load.
