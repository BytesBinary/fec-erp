# Workflows and diagrams

How the main processes work, as diagrams (Mermaid; they render on GitHub and in most Markdown viewers). Each diagram is checked against the code; the decision records are in `DECISIONS.md`.

## 1. Student journey

```mermaid
flowchart LR
  A[Student added<br/>panel / MCP / assistant] --> B[Welcome email<br/>+ result pull queued]
  B --> C{Profile complete?}
  C -- no --> D[Profile gate<br/>complete 16 fields + photo]
  D --> C
  C -- yes --> E[Results page<br/>published semesters, CGPA,<br/>official portal results]
  E --> F{Eligible for clearance?}
  F -- no --> G[Reasons shown]
  F -- yes --> H[Apply: CLR-YYYY-NNNNNN]
  H --> I[Approval chain]
  I --> J[Ready for collection]
  J --> K[Print, hand over, collected]
  K --> L[Anyone verifies the<br/>certificate by QR]
```

## 2. Clearance request states

```mermaid
stateDiagram-v2
  [*] --> submitted: student applies
  submitted --> pending: chain starts
  pending --> pending: stage approves (next stage)
  pending --> rejected: stage rejects with remarks
  rejected --> pending: student resubmits
  pending --> ready_for_collection: last stage approves
  ready_for_collection --> printed: print (reprints marked DUPLICATE)
  printed --> collected: hand-over (ID verified)
  submitted --> cancelled: student cancels (before first approval)
  pending --> cancelled: super admin cancels with reason
  collected --> [*]
  cancelled --> [*]
```

Default chain: **hall → library → department → head of institution**. Non-residents skip the hall stage; stages can be turned off, re-ordered or made skippable (Settings → Clearance stages). One class, `ClearanceService::transition()`, is the only writer of the status and uses an optimistic version lock.

```mermaid
flowchart TB
  R[Approver opens request] --> Q{Holds the stage's role<br/>AND role scope covers the student?}
  Q -- no --> X[Forbidden]
  Q -- yes --> S[Signature snapshot copied,<br/>SHA-256 stored]
  S --> H[Hash of previous + this decision<br/>added to the chain]
  H --> N[Next approver notified]
```

## 3. Results workflow

```mermaid
stateDiagram-v2
  [*] --> draft: teacher enters marks
  draft --> submitted: teacher submits (all students have marks)
  submitted --> approved: department head approves
  approved --> published: publisher publishes the semester
  published --> [*]
```

Students (and the student-facing read channels) only ever see `published` results. CGPA uses the best attempt of a repeated course, counts a failed course as 0.00, and rounds half-up to 2 decimals.

## 4. Official results from the university portal

```mermaid
flowchart TB
  subgraph Student added
    A1[Student created] --> A2[StudentObserver]
    A2 --> A3[ResultPull queued<br/>skipped if no / non-numeric<br/>registration number]
    A3 --> A4[Job: exams in admission-year window]
    A4 --> A5[POST lookup per exam<br/>2 s pacing]
    A5 --> A6{Parser}
    A6 -- result --> A7[Save grades, GPA, CGPA,<br/>mark Retake / Improved / Declined]
    A6 -- not verified --> A8[Waiting: re-check after 3 and 7 days]
    A6 -- unknown page --> A9[Failed with reason]
  end
  subgraph Daily 06:00
    B1[Read exam list<br/>3 requests] --> B2{New exam id?}
    B2 -- no --> B6[Done]
    B2 -- yes --> B3[Publication candidate]
    B3 --> B4[Probe students look it up]
    B4 -- found --> B5{Shadow mode?}
    B5 -- yes --> B7[Wait for admin: Run now]
    B5 -- no --> B8[Queue every eligible student]
    B4 -- not verified --> B9[Awaiting: daily for 14 days, then weekly]
    B4 -- error / unknown page --> B10[Health alert,<br/>never treated as not published]
  end
```

## 5. Email notification pipeline

```mermaid
flowchart LR
  E[Module emits event<br/>in the same DB transaction] --> O[(outbox_events)]
  O --> P[ProcessOutbox<br/>every minute / after commit]
  P --> R{Rule enabled?}
  R -- no --> Z[nothing]
  R -- yes --> U[Resolve recipients<br/>department / hall boundaries]
  U --> T[Render template<br/>whitelisted placeholders]
  T --> G{Secret guard}
  G -- looks like credential --> BL[blocked]
  G -- ok --> M{Mode}
  M -- digest --> H[held → one email per person at 07:30]
  M -- immediate --> Q[(email_deliveries<br/>unique dedupe key)]
  H --> Q
  Q --> S[SendEmailDelivery<br/>5 tries, backoff 1/5/15/60 min]
  S -- ok --> SENT[sent]
  S -- fails --> F[failed → admin Retry]
```

## 6. Sign-in and security checks (every request)

```mermaid
flowchart TB
  L[Login] --> T[TrackUserSession<br/>record valid, not revoked / expired]
  T --> A[Authenticate]
  A --> M{2FA enabled?}
  M -- yes, not passed --> C[2FA challenge<br/>or trusted device]
  M -- no --> R{Role requires 2FA?}
  R -- yes --> SU[2FA setup]
  R -- no --> P{Student with incomplete profile?}
  C --> P
  SU --> P
  P -- yes --> PG[Profile gate]
  P -- no --> OK[Page / Livewire update / API]
```

The same chain runs on every Livewire update, so a page left open after logout, revocation or deactivation stops working (HTTP 419 → reload to login).

## 7. How an AI client or the assistant acts

```mermaid
sequenceDiagram
  participant U as User / AI client
  participant G as Gateway (MCP / assistant)
  participant X as ToolExecutor
  participant S as Domain service
  U->>G: tools/call (token) or chat message
  G->>X: tool + arguments
  X->>X: permission, profile gate, read-only integration,<br/>strict validation
  alt write or destructive
    X-->>U: dry-run preview / confirmation card
    U->>X: confirm=true (idempotency key)
  end
  X->>S: same service the web screens use
  S->>S: authorize(permission, scope) + audit (channel mcp / assistant)
  S-->>U: structured result + summary
```

## 8. Creating an AI integration

```mermaid
flowchart LR
  A[User enables 2FA] --> B[Wizard: choose client, name,<br/>access level, expiry]
  B --> C[Confirm with fresh 2FA code]
  C --> D[Token shown once,<br/>stored as SHA-256]
  D --> E[Paste snippet into the client]
  E --> F[Every request: token valid,<br/>not expired, user active, 2FA still on]
```
