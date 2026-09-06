# mobiST POS - Privacy Policy

Effective: 6 September 2026
Product: mobiST POS - Retail POS & Inventory Management System
Developer: mobiST Technologies

## 1. Scope

This Privacy Policy explains how mobiST POS handles information when a shop installs and uses the Windows desktop software.

mobiST POS is designed as an offline/local-first product. Routine shop data is stored on the shop's own Windows PC rather than in a mobiST Technologies central operational database.

## 2. Information processed by the software

Depending on the features used, the software may process:

- Shop/business identity, contact and invoice settings.
- Local Owner, Manager and Salesperson account information.
- Customer details such as name, mobile number and optional CNIC.
- Product, inventory, IMEI/serial, supplier and purchase information.
- Sales, payments, invoices, returns, exchanges and expense records.
- Reports, audit/activity records and backup metadata.
- A privacy-limited Device ID and licensing facts needed for trial/license enforcement and support.

Passwords are not stored in plaintext.

## 3. Local storage and control

The shop controls its local business database, Windows device, user accounts, backup destinations and access permissions.

Uninstalling the application does not automatically mean all business data or backups are deleted. Shops should manage retention and deletion according to their own business/legal needs.

## 4. Optional Google Drive backup

Google Drive backup is optional and is initiated/configured by the shop Owner.

- Authorization uses Google OAuth in the system browser.
- The requested Google Drive scope is `https://www.googleapis.com/auth/drive.file`.
- No customer Google password is collected by mobiST POS.
- No OAuth client secret or service-account credential is required by the customer application.
- The Google refresh token is stored locally using Windows-protected storage; short-lived access tokens are not persistently stored.
- Verified backup packages are uploaded to an app-created `mobiST POS Backups` folder in the Google account authorized by the shop.
- mobiST Technologies does not receive, proxy, centrally store or control those shop backups.
- Disconnecting Google Drive removes local authorization and disables automatic upload, but does not delete backup files already stored in the shop's Google Drive.

Use and transfer of information received from Google APIs by mobiST POS will adhere to the Google API Services User Data Policy, including the Limited Use requirements.

## 5. Optional sharing and external services

If the shop chooses WhatsApp or email invoice sharing, the selected invoice/customer information is passed to the external app/service chosen by the user. Those services are governed by their own privacy terms.

mobiST POS does not silently transmit routine business data for advertising, analytics or a central operational database.

## 6. Support and diagnostics

The application can provide non-secret licensing/support information when the shop deliberately exports or shares it for support. Support diagnostics are designed not to include passwords, private signing material or routine customer/business records.

## 7. Security measures

The product uses safeguards appropriate to its local/offline design, including salted adaptive password hashing, Windows-protected storage for supported secrets/tokens, role-based access, protected licensing state and audit controls.

No security system can guarantee absolute protection. Shops remain responsible for Windows account security, device access, malware protection and maintaining verified backups.

## 8. Retention and deletion

Local records remain under the shop's control until the shop deletes them or removes the relevant local data. Google Drive backups remain in the connected shop account until that account owner deletes them.

## 9. Children

mobiST POS is a business retail product and is not directed to children.

## 10. Changes and contact

This policy may be updated when product features, legal requirements or third-party integrations materially change. The effective date will be updated when a revised policy is published.

Privacy or support enquiries: `mobisttech@gmail.com`

Copyright © 2026 mobiST Technologies. All rights reserved.
