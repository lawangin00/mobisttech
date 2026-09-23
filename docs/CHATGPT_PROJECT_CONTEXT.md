# ChatGPT Project Context ? Mobisttech

This is a project-scoped deployment template, not an active ChatGPT configuration. Copy the exact marker below into the **Project Instructions of the corresponding selected ChatGPT Project**; retain its existing instructions. Never paste it into account-level Custom Instructions, another project, or a generic chat.

```text
PROJECT_CONTEXT_BINDING_V1: PROJECT_ID=282dba2f-a2d9-47e8-aa8d-e499fbe1706c | REPOS=lawangin00/mobisttech
```

On the first project-bound command in a new chat, the universal first-bind gate must verify this UUID and the COMPLETE repository set against the canonical `docs/PROJECT_IDENTITY.json` at the remote HEAD and any selected local root. After exact verification, establish the Session Project Lock and execute that original command in the same turn. A mismatch hard-stops; an unavailable explicit marker retains the one-time numbered project selection fallback. `Refresh`/`Help` do not bind projects.

For multi-repository projects, all listed repositories belong to one Project ID; the member repository containing this file is not a separate ChatGPT project merely because it has a distinct remote. Committing this template does NOT change ChatGPT Project Instructions; the user must install its marker in the ChatGPT UI.
