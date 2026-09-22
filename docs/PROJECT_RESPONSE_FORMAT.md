# Mobisttech — binding owner-approved response format

## Command-specific output (binding)

Only `Y` / `Proceed` / `Resume` and explicitly authorized task execution/continuation use five adjacent visible lines: `Completed: <verified>`, `Current: <point/status>`, `Next: <pending>`, literal `Proceed? Y/N`, fresh Asia/Karachi timestamp. First three are concise truthful titles; only optional verified DONE count in Current; no extra narratives, headings or blank lines.

`Next` is read-only: exactly THREE adjacent visible lines, `Next: <exact verified In Progress point, else first Pending point; None if absent>`, `Proceed? Y/N`, fresh timestamp. Do NOT execute the Next point on this command. `Status`, `Progress`, `Verify`, audits, `Sync Check`, `Checkpoint`, `Refresh`, Help and manual handoffs retain their specialized universal alias output; never force five lines on these commands. User-requested alternate answer types remain allowed without expanding execution authorization.

## Rendering and pre-send gate

Select output contract from exact resolved alias before composing. Use plain actual newlines in Local Work without markup; other surfaces use supported native line breaks or Markdown hard breaks if ordinary newlines collapse. Never print renderer tags, code fences or blank paragraphs. Check visible field count/order for this alias, verified project facts and fresh final timestamp. Formatting does not change project stage, identity or task authorization; source tests cannot guarantee live native-chat rendering.

## Enforcement boundary

This Git-tracked contract is a durable instruction for agents working on this repository, NOT an API hook capable of intercepting every ChatGPT message. Repository CI, a local linter or another script cannot guarantee what the ChatGPT conversation renderer will display unless that actual response is routed through the validator. Never claim that changing this file alone creates a software-enforced 100% lock. The observable check is the actual five-line message shown to the owner.

Scope: Mobisttech project `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`, `lawangin00/mobisttech` only. This formatting-only control update must not reopen/advance MT-7.5 W01, mutate application/business data, modify separate projects or rewrite prior chats.
