# Roadmap Word mirror

Canonical Markdown: `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`. The Word output uses the same folder and basename.

`build_roadmap_docx.py` uses Python and python-docx; it reads only the new monorepo roadmap and writes its DOCX mirror. Markdown and Word visible body blocks and task IDs are compared. The helper also verifies one `Dependencies:` line per executable point and rejects live `Status:`/`Stage status:` markers because live execution state belongs in `docs/PROJECT_IMPLEMENTATION_STATUS.md`.

Layout: compact_reference_guide preset; Letter, 1-inch margins, Calibri 11, 1.25 line spacing; memo_masthead title, 23 pt navy, no decorative rule. Stage headings start on a new page; header/footer use 9 pt gray named overrides. Tables/lists are not used in the current roadmap.

Run the helper, render Word and inspect every rendered page only when the roadmap structure/content materially changes. Routine point/stage progress updates only the implementation ledger and must not regenerate the DOCX. Never edit Word as an independent source. Keep generated QA artifacts under `.local/` and do not commit them.
