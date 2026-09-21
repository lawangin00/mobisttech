# Mobisttech — Owner-approved project response format

Approved example (the exact five-line layout selected by the owner; example values are historical, not a current status report):

```text
Completed: W01 backend regression and website build verification
Current: MT-7.5 W01 — In Progress (13/27 DONE)
Next: Verify terminal CI result and continue W01 Customer browser acceptance
Proceed? Y/N
4:30 PM, 21-Sep-26
```

## Binding output contract

For ordinary Mobisttech project execution, checkpoint, continuation and progress replies, render exactly five adjacent text lines, in this order, with a single hard line break between them and no blank lines, heading, preamble, commentary, code fence, bullets or trailing text:

```text
Completed: <last actually verified completed checkpoint or work>
Current: <exact active stage/point ID and title or short family> — <verified state> (<verified DONE count>/<verified total> DONE)
Next: <first genuinely pending recovery/continuation action>
Proceed? Y/N
<h:mm AM/PM, d-MMM-yy in the user's local time>
```

The example shows the precise visual layout; replace example values with fresh, truthful evidence each turn. Never label an incomplete point complete or invent a completed action to fill the first line. A current in-progress point may be shown on the Current line while Completed names only its last verified sub-checkpoint. Obtain a fresh local timestamp as the final evidence operation; the timestamp is the final line. The four labels and their order, spaces, punctuation, single-line-per-field layout and compact single line spacing are fixed. If a field would wrap on screen because of window width, do not introduce manual paragraph breaks or empty lines. When the platform collapses plain-text newlines, use presentation that preserves line breaks without adding blank line spacing. No alternative inherited, earlier-chat or cached formatting template governs these ordinary project replies; this owner-approved project-specific layout supersedes such presentation examples, without changing the actual work, safety, project identity, state or authorization gates.

Explicitly different command families (for example global execution-mode commands, read-only handoffs or user-requested detailed reports) must preserve their factual command semantics; do not misrepresent a global command or a read-only operation as an executable point completion merely to populate the five fields. This document is a presentation rule only. It does not purge previous messages, saved account memory, external copies or Git history.

Scope: only the Mobisttech project (`282dba2f-a2d9-47e8-aa8d-e499fbe1706c`, `lawangin00/mobisttech`). It does not modify the separate POS-IMS project or the universal references repository. No project implementation or active W01 acceptance is advanced by this documentation-only control update.
