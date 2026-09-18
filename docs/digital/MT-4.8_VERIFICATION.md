# MT-4.8 Verification

- Point: MT-4.8 - Digital operations administration interfaces
- Digital Operations workspace uses the existing Admin realm and separate permissions for services, consultations, leads, projects, proposal approval, client files and conversion reporting.
- Services/packages/add-ons remain authoritative through DigitalServiceLeads; historical enquiry monetary snapshots are preserved.
- Consultation configuration supports timezone and weekly availability while external calendar sync remains disabled until an approved provider exists.
- Lead administration covers assignment, status, follow-up, consultation state, reference files and conversion to client project.
- Project administration covers lifecycle, proposal revisions/approval, exact PKR milestone schedules, paid-history repricing protection, private client file exchange and integrity-checked admin downloads.
- Private download responses never expose object keys/storage paths and verify stored SHA-256 before returning content.
- Website mode history remains visible; inactive digital creation stays blocked by existing capability policy while approved historical projects/proposals/files remain readable.
- Conversion reporting delegates to aggregate-only output and excludes customer names, emails, mobiles, requirements and internal notes.
- Focused HTTP acceptance: 3 tests / 55 assertions PASS.
- Targeted Digital Operations Playwright: 1/1 PASS.
- Affected digital regression: 20 tests / 218 assertions PASS.
- Full backend regression: 241 tests / 7401 assertions PASS.
- Full Playwright suite: 8/8 PASS.
- Production Vite build: PASS, 577 modules transformed with DigitalOperations included in the client resolver graph.
- Final short gates: Composer validate --strict PASS; Composer platform requirements PASS; scoped Pint PASS; backend TypeScript typecheck PASS; git diff check PASS.
- Browser recovery: initial stale bundle omitted DigitalOperations, then test-only textarea/proposal assertions were corrected to the rendered UI contract. No unresolved MT-4.8 production defect remains.
