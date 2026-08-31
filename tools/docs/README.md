# Roadmap Word mirror

Canonical Markdown: `docs/PROJECT_IMPLEMENTATION_ROADMAP.md`. Word output same folder aur basename ke saath hai.

`build_roadmap_docx.py` Python aur python-docx use karta hai; sirf new monorepo roadmap read aur uski DOCX write karta hai. Codex mein bundled artifact Python/runtime use karein. Markdown aur Word ke tamam visible body blocks, task IDs aur status count compare hote hain. Unsupported list/table/code forms fail honge taake silently content drop na ho.

Layout: compact_reference_guide preset; Letter, 1-inch margins, Calibri 11, 1.25 line spacing; memo_masthead title, 23 pt navy, no decorative rule. Stage headings new page par; header/footer 9 pt gray named overrides hain. Tables/lists is initial roadmap mein nahi hain.

Roadmap edit ke baad helper run karein, Word render karein aur har rendered page inspect karein. Word ko independently edit na karein. Generated QA artifacts `.local/` mein rakhein aur commit na karein.
