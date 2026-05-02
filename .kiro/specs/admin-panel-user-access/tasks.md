# Task List: Admin Panel, User Access Control & Device Sharing

This document outlines the coding tasks required to implement the Admin Panel, RBAC, Device Sharing, and Spike Detection features.

## 1. Database Schema & Core Auth (RBAC)
- [x] 1.1 Implement database migrations for new tables and columns.
- [x] 1.2 Update login and session handling to support roles.
- [x] 1.3 Create admin authorization middleware.

## 2. Admin Panel - User Management
- [x] 2.1 Implement the User Management table view.
- [x] 2.2 Add sorting, searching, and pagination to the User table.
- [x] 2.3 Implement Role Editing and User Deletion.

## 3. Admin Panel - Device & Token Management
- [x] 3.1 Implement the Device Management table view.
- [x] 3.2 Add sorting, searching, and pagination to the Device table.
- [x] 3.3 Implement Token Generation logic.
- [x] 3.4 Implement Token Listing and Revocation.

## 4. User Dashboard - Device Sharing
- [x] 4.1 Implement Token Redemption UI.
- [x] 4.2 Implement Token Redemption Backend.
- [x] 4.3 Update Dashboard to display Shared Devices.
- [x] 4.4 Implement Shared Device Removal.

## 5. Device Dashboard & Access Restrictions
- [x] 5.1 Update Device Dashboard Access Checks.
- [x] 5.2 Implement Viewer-only UI restrictions.

## 6. MQTT Worker - Spike Detection & Logging
- [x] 6.1 Implement Spike Detection in handleIncubator.
- [x] 6.2 Update Dashboard to display Significant Events.
