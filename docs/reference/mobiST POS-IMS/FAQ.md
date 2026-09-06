# mobiST POS - Frequently Asked Questions

Version 1.0.0
Updated: 6 September 2026

## General

### What is mobiST POS?
mobiST POS is a Windows desktop Point of Sale and Inventory Management System for mobile phones, tablets, accessories and similar retail businesses.

### Does mobiST POS require continuous internet?
No. Core setup, inventory, purchases, sales, customers, invoices, reports, local backup/restore and normal post-activation use are designed to work offline. Internet is needed only for optional online features such as Google Drive backup or external invoice sharing.

### Where is my shop data stored?
Routine business data is stored on the shop's local Windows PC. mobiST Technologies does not operate a central operational database for normal POS data.

### Which staff roles are available?
V1 includes Owner, Manager and Salesperson roles with different permissions.

## Backup and Google Drive

### Does mobiST POS support local backups?
Yes. The software supports verified local backups and guarded restore. Local backup remains important even if Google Drive backup is enabled.

### Where do Google Drive backups go?
They go to the Google Drive account explicitly connected by the shop Owner, under the app-created `mobiST POS Backups` folder.

### Are backups stored in mobiST Technologies' Google Drive?
No. The customer authorizes the customer's own Google account. mobiST Technologies does not receive or centrally store those backups under the current V1 design.

### Does mobiST POS need my Google password?
No. Google authorization happens in the system browser through OAuth. mobiST POS does not ask for or store the customer's Google password.

### What Google Drive permission does the app request?
The app uses the limited `drive.file` scope so it can work with Drive files created or explicitly used through mobiST POS rather than requesting unrestricted access to the whole Drive.

### What happens if Google Drive is disconnected?
Local authorization is removed and automatic cloud upload is disabled. Existing backup files already in the customer's Google Drive are not automatically deleted.

## Licensing

### Is there a trial?
Yes. Eligible devices can start the built-in 7-Day Trial according to the active Trial Generation policy.

### What paid license terms are available?
1 Year, 5 Years, 10 Years and Lifetime.

### Does reinstalling reset the trial or paid license term?
No. Reinstalling does not automatically create a new trial for the same Trial Generation and does not extend the original paid-license term.

### What if hardware changes?
A material hardware change may require an approved license reissue/recovery process. The About & License screen provides the Device ID and support information needed for that process.

## Daily use and support

### Can mobiST POS track IMEI/serialized devices?
Yes. Serialized phones/tablets can be tracked separately from quantity-based stock such as accessories.

### Can a sale use more than one payment method?
Yes. Split-payment allocation is supported as long as the allocations equal the sale total.

### Can invoices be shared?
Yes. Where configured, invoices can be prepared for WhatsApp and/or email sharing. The user should verify the recipient before sending.

### Does uninstalling delete my shop data?
The product is designed to preserve business data through normal uninstall/upgrade flows. Keep verified backups before maintenance or migration work.

### Which Windows versions are supported?
Windows 11 x64 is the primary supported platform. Windows 10 x64 and Windows 10 x86/32-bit are compatibility-tested targets subject to their operating-system support limitations.

### Where is the User Manual?
The final User Manual PDF is bundled with the installed application and can be opened from the main window without internet access.

### How do I get support?
Use the Device ID/support information from **About & License** when relevant and contact `mobisttech@gmail.com`. Never send passwords or Google access tokens in ordinary support messages.

Copyright © 2026 mobiST Technologies. All rights reserved.
