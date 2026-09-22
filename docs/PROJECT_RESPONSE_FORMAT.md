# Mobisttech — binding owner-approved response format

## Exact ordinary project-status output

Render exactly **five visible, adjacent lines**, one field per line and no blank lines or other visible content:

```text
Completed: <short title of actually completed work>
Current: <short title of active work and verified status>
Next: <short title of the first pending action>
Proceed? Y/N
<h:mm AM/PM, d-MMM-yy in Asia/Karachi>
```

The example above is a template, NOT current project state. Keep the labels, order, casing and `Proceed? Y/N` literal. Each of the first three values must be a **single concise title**, not a summary: no test counts/results, failure explanations, evidence narratives, commit lists, commentary, headings, bold/italic styling, bullets or added fields. A compact verified stage-progress count may appear in `Current` when relevant, but never expand it into a paragraph. `Completed` must describe only work actually completed; `Current` must never present an open point as complete; `Next` names only the immediate pending action. Do not advance the roadmap or change ledger counts for a formatting-only task. Obtain a fresh Asia/Karachi local timestamp after the last project-state action.

## Rendering gate — five VISUAL lines, not just five source lines

Before sending a status response, ensure the chat renderer preserves all four intervening line breaks. In Local Work use five plain text lines with four actual newlines and no wrapper tags. For other surfaces use supported native text layout where available; when plain newlines collapse use explicit Markdown hard breaks (two trailing spaces followed by newline). Never print literal component/markup syntax, code fences, headings, borders or extra lines; do not concatenate partial messages. There must be exactly one displayed line for each field, no merged fields, and no blank line between fields. Shorten long field values to prevent visual wrapping on a narrow screen. Do not send half a template before the project task finishes; construct and check the complete five-line response as one output.

Pre-send checklist: (1) exactly five visible lines, (2) labels in fixed order, (3) first three entries are short titles only, (4) no formatting or extra text, (5) fresh local timestamp last. If any check fails, reformat BEFORE sending, without asking the owner to correct it. The current canonical universal five-line response contract is authoritative across all managed projects; this local document records the same owner-approved layout and must not override a newer universal format. It does not override explicit requests for a different type of response, specialized control-command semantics or safety/project identity gates.

## Enforcement boundary

This Git-tracked contract is a durable instruction for agents working on this repository, NOT an API hook capable of intercepting every ChatGPT message. Repository CI, a local linter or another script cannot guarantee what the ChatGPT conversation renderer will display unless that actual response is routed through the validator. Never claim that changing this file alone creates a software-enforced 100% lock. The observable check is the actual five-line message shown to the owner.

Scope: Mobisttech project `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`, `lawangin00/mobisttech` only. This formatting-only control update must not reopen/advance MT-7.5 W01, mutate application/business data, modify separate projects or rewrite prior chats.
