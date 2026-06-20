export const meta = {
  name: 'dbmanager-datatype-recon',
  description: 'Exhaustively map where every data type lives across DBManager mechanisms, audit bulk-edit, find missed touchpoints',
  phases: [
    { title: 'Recon', detail: 'one agent per mechanism traces type touchpoints' },
    { title: 'Critic', detail: 'completeness critic greps for missed touchpoints' },
  ],
}

const CORE = '10-Projects/DBManager/code/core'

const MAP_SCHEMA = {
  type: 'object',
  additionalProperties: false,
  required: ['mechanism', 'summary', 'dispatch_model', 'touchpoints', 'new_type_integration_steps', 'gaps_risks'],
  properties: {
    mechanism: { type: 'string' },
    summary: { type: 'string', description: 'How this mechanism works and how it dispatches on data type' },
    dispatch_model: { type: 'string', description: 'switch/match on code | polymorphism | strategy class | hardcoded conditionals | data-driven config — describe precisely with file:line' },
    hardcoded_type_lists: {
      type: 'array', description: 'Every place where the set of types is enumerated/hardcoded (arrays, match arms, in_array, allowedTypes)',
      items: { type: 'object', additionalProperties: false, required: ['file', 'location', 'types', 'detail'], properties: {
        file: { type: 'string' }, location: { type: 'string', description: 'method/line' }, types: { type: 'array', items: { type: 'string' } }, detail: { type: 'string' } } },
    },
    touchpoints: {
      type: 'array', description: 'Every concrete place this mechanism handles a data type',
      items: { type: 'object', additionalProperties: false, required: ['file', 'location', 'role', 'type_handling'], properties: {
        file: { type: 'string' }, location: { type: 'string' }, role: { type: 'string' }, type_handling: { type: 'string', description: 'how type code/content shape is used here; note if phone-only or generic' } } },
    },
    new_type_integration_steps: { type: 'array', description: 'Concrete steps required to make a NEW type (social, address) flow correctly through THIS mechanism', items: { type: 'string' } },
    gaps_risks: {
      type: 'array', items: { type: 'object', additionalProperties: false, required: ['issue', 'severity', 'detail'], properties: {
        issue: { type: 'string' }, severity: { type: 'string', enum: ['high', 'medium', 'low'] }, detail: { type: 'string' } } },
    },
    content_shape: { type: 'string', description: 'If known: the JSON content shape used for each type in DataValue.content for this mechanism' },
  },
}

const AUDIT_SCHEMA = {
  type: 'object',
  additionalProperties: false,
  required: ['mechanism', 'summary', 'dispatch_model', 'touchpoints', 'new_type_integration_steps', 'gaps_risks', 'flow', 'confusion_points', 'recommendations', 'highlighting_state'],
  properties: {
    mechanism: { type: 'string' },
    summary: { type: 'string' },
    dispatch_model: { type: 'string' },
    touchpoints: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['file', 'location', 'role', 'type_handling'], properties: { file: { type: 'string' }, location: { type: 'string' }, role: { type: 'string' }, type_handling: { type: 'string' } } } },
    new_type_integration_steps: { type: 'array', items: { type: 'string' } },
    gaps_risks: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['issue', 'severity', 'detail'], properties: { issue: { type: 'string' }, severity: { type: 'string', enum: ['high', 'medium', 'low'] }, detail: { type: 'string' } } } },
    flow: { type: 'array', description: 'Ordered user+code steps of a bulk edit from selection to apply to rollback', items: { type: 'string' } },
    confusion_points: { type: 'array', description: 'Where a user can be confused or make a mistake about what will change', items: { type: 'object', additionalProperties: false, required: ['where', 'problem', 'severity'], properties: { where: { type: 'string' }, problem: { type: 'string' }, severity: { type: 'string', enum: ['high', 'medium', 'low'] } } } },
    type_ambiguity_risks: { type: 'array', items: { type: 'string' } },
    recommendations: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['title', 'priority', 'rationale'], properties: { title: { type: 'string' }, priority: { type: 'string', enum: ['P0', 'P1', 'P2'] }, rationale: { type: 'string' }, effort: { type: 'string' } } } },
    highlighting_state: { type: 'string', description: 'Exact current implementation of visually highlighting changed values in preview/single edit, with file:line and gaps' },
  },
}

const CRITIC_SCHEMA = {
  type: 'object', additionalProperties: false, required: ['coverage_assessment', 'missed_touchpoints'],
  properties: {
    coverage_assessment: { type: 'string' },
    missed_touchpoints: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['file', 'location', 'why_relevant', 'mechanism'], properties: { file: { type: 'string' }, location: { type: 'string' }, why_relevant: { type: 'string' }, mechanism: { type: 'string' } } } },
    type_code_inventory: { type: 'array', description: 'Every distinct hardcoded type-code list found anywhere, file:line', items: { type: 'string' } },
  },
}

const COMMON = `Project: DBManager (Laravel + Livewire). Repo root is the cwd. Code lives under ${CORE}.
The data-type system: table value_types(code) with codes [phone, messenger, price, address, social, text]; values stored in data_values with JSON column content (cast to array) and a value_type_id FK. Only phone, messenger, price are fully working today. address, social, text exist in the seeder but are NOT wired into the mechanisms. There is also a reserve phone variant referenced as 'phone_reserve'.
Your job: trace YOUR assigned mechanism with extreme precision. Read the FULL relevant files (do not skim), follow references, and grep for additional usages of type codes ('phone','messenger','price','address','social','text','phone_reserve') and of DataValue/value_type across app/ and resources/. Report EVERY place a data type is special-cased, EVERY hardcoded list of types, and the EXACT concrete steps needed to make a NEW type (social, address) flow correctly through your mechanism. Be exhaustive — a missed touchpoint means a new type silently breaks. Use file:line references. Your return value is data, not prose for a human.`

const MECHANISMS = [
  { key: 'type-registry', label: 'recon:type-registry', schema: MAP_SCHEMA, prompt: `${COMMON}

MECHANISM: Data model & type-dispatch ARCHITECTURE (the foundation).
Read deeply: ${CORE}/app/Models/DataValue.php, ValueType.php, PhoneNumber.php, PhoneSlot.php, GeoTag.php, NumberEntry.php, Site.php; ${CORE}/database/migrations/2026_06_11_000002_create_dictionaries.php, 2026_06_11_000003_create_data_values.php, 2026_06_11_000004_create_phones.php; ${CORE}/database/factories/ (all); ${CORE}/database/seeders/ValueTypeSeeder.php, DemoDataSeeder.php.
Answer precisely: How is DataValue.content shaped for EACH type (the exact keys per type)? Is there ANY per-type strategy/config/enum class, or is every type special-cased inline everywhere? Where is the canonical list of type codes? What model relations does a phone value use (PhoneSlot/PhoneNumber/NumberEntry) that a new type would NOT have? Document the cleanest available extension point for adding a typed value (social: platform+handle/url; address: structured or text).` },

  { key: 'bulk-edit', label: 'audit:bulk-edit', schema: AUDIT_SCHEMA, effort: 'high', prompt: `${COMMON}

MECHANISM: BULK EDIT — and you must ALSO produce a critical UX audit.
Read the FULL files: ${CORE}/app/Livewire/BulkOperations.php (1024 lines), ${CORE}/resources/views/livewire/bulk-operations.blade.php (601 lines). Also read any Concerns it uses (HandlesScopeDecision, UsesEditLock) and how it builds previewRows, report, stats, and rollback.
Map: every per-type branch, the allowed-operations list per type, how the preview rows are built and how changed cells are highlighted, how new_value/new_state/new_geo/new_key are computed, how apply and rollbackBatch work, how batch_id/audit is written.
CRITICAL AUDIT (this is a key deliverable): walk the full flow (selection -> choose type/operation -> preview -> apply -> rollback). Identify EVERY point where a user can be confused or make a mistake about exactly what will change; ambiguity between data types during a bulk change; lack of predictability (missing/weak preview, confirmation, undo). Document the EXACT current state of visual highlighting of changed values (file:line, css classes) and its gaps. Give prioritized recommendations (P0/P1/P2) to make bulk edit predictable and nearly confusion-free. Be maximally critical.` },

  { key: 'single-edit-crud', label: 'recon:single-edit-crud', schema: MAP_SCHEMA, prompt: `${COMMON}

MECHANISM: Single-record CREATE / EDIT / DELETE and the values grid.
Read FULL: ${CORE}/app/Livewire/ValueEditor.php (+ resources/views/livewire/value-editor.blade.php), ${CORE}/app/Livewire/ValuesGrid.php (1934 lines, + values-grid.blade.php), ${CORE}/app/Livewire/SlotPanel.php (+ slot-panel.blade.php), ${CORE}/app/Livewire/MessengerPanel.php (+ messenger-panel.blade.php).
Answer: How is the editor form chosen/rendered per type? How are create/edit/delete persisted into data_values.content per type? Where would a NEW type's editor UI + persistence hook in? How does the grid render a value cell per type? Document per-type rendering branches and the steps to render+persist+delete social and address.` },

  { key: 'audit-logging', label: 'recon:audit-logging', schema: MAP_SCHEMA, prompt: `${COMMON}

MECHANISM: Audit / change history / logging / restore.
Read FULL: ${CORE}/app/Models/AuditLog.php, ${CORE}/app/Livewire/AuditManager.php (+ audit-manager.blade.php), ${CORE}/app/Services/Audit/AuditRestorer.php. Then grep across app/ for every AuditLog::create / audit log write and how 'type' is recorded and displayed.
Answer: How is a change logged (action names like bulk.*, value.*; subject_type/subject_id; batch_id; before/after content)? How does the audit UI display the data type and human-readable value per type? How does restore reconstruct a value per type? Where does a NEW type need to plug in so its changes log correctly, display a sensible label, and restore correctly? Note any phone-only assumptions in display/restore.` },

  { key: 'validation', label: 'recon:validation', schema: MAP_SCHEMA, prompt: `${COMMON}

MECHANISM: Validation & value normalization per type.
Grep and read every validation path: ${CORE}/app/Livewire/ValueEditor.php (validate/rules ~line 160), BulkOperations.php, SlotPanel.php, MessengerPanel.php; any FormRequest in app/Http; any normalization (phone E164, price formatting, messenger slot keys).
Answer: Where are per-type rules defined and how are they selected by type? How is content normalized before save per type? What is the validation contract a new type must satisfy (social: platform enum + url/handle format; address: required structured fields OR free text)? List exactly where social/address validation rules must be added, and whether validation is centralized or scattered.` },

  { key: 'search-export-publish', label: 'recon:search-export-publish', schema: MAP_SCHEMA, prompt: `${COMMON}

MECHANISM: Search / filters / export / publishing to sites.
Read FULL: ${CORE}/app/Admin/SiteGridReader.php, ${CORE}/app/Services/Publishing/SitePayloadCompiler.php, ${CORE}/app/Services/Publishing/GeoDatabasePublisher.php, ${CORE}/app/Services/Publishing/BridgePublisher.php; and the filter/search code in ValuesGrid.php and BulkOperations.php (targeting/matching selection).
Answer: How does each type get compiled into the published site payload (the exact JSON shape the site consumes)? How are values filtered/searched/selected for bulk targeting per type? Where must a NEW type be added so it is published to sites and is filterable/searchable? Note any type allow-lists in publishing/targeting.` },

  { key: 'permissions', label: 'recon:permissions', schema: MAP_SCHEMA, prompt: `${COMMON}

MECHANISM: Access control / permissions, especially per-type visibility.
Read FULL: ${CORE}/app/Livewire/AccessManager.php (+ access-manager.blade.php), ${CORE}/app/Models/UserSiteAccess.php, UserSiteGroupAccess.php, AccessControl (grep), User.php; migrations add_user_access_control, add_can_view_prices_to_access, add_log_access_matrix, add_can_view_history.
Answer: Is visibility/edit permission per data-type? (can_view_prices implies yes for price.) How is a type gated in UI and queries? Does adding social/address require a new permission flag/migration, or are they ungated by default? Specify exactly what (if anything) a new type needs in the access layer, and the security implication of getting it wrong.` },

  { key: 'highlighting', label: 'recon:highlighting', schema: MAP_SCHEMA, prompt: `${COMMON}

MECHANISM: Visual highlighting of data that is about to change (single edit + bulk preview).
Read: ${CORE}/resources/views/livewire/bulk-operations.blade.php, value-editor.blade.php, values-grid.blade.php; any CSS in resources/ and tailwind usage; the $previewRows 'changed' flags and new_* fields.
Answer: EXACTLY how is changed data currently highlighted (which rows/cells, which css classes/colors, only-on-change conditions) in bulk preview and in single edit? Where is highlighting WEAK or MISSING (e.g. no before/after, no per-cell highlight, no indication in single edit, reserve-phone special cases)? Document the concrete current state with file:line, and the gaps to fix so changed data is ALWAYS clearly highlighted for both single and bulk edits, including for new types.` },
]

phase('Recon')
const recon = await parallel(MECHANISMS.map(m => () =>
  agent(m.prompt, { label: m.label, phase: 'Recon', schema: m.schema, ...(m.effort ? { effort: m.effort } : {}) })
    .then(r => ({ key: m.key, result: r }))
))

const recded = recon.filter(Boolean)
log(`Recon done: ${recded.length}/${MECHANISMS.length} mechanisms mapped`)

phase('Critic')
const coveredFiles = JSON.stringify(recded.map(r => ({ mechanism: r.key, touchpoints: (r.result?.touchpoints || []).map(t => `${t.file}:${t.location}`) })))
const critic = await agent(`${COMMON}

You are a COMPLETENESS CRITIC. Other agents already mapped these mechanisms and touchpoints:
${coveredFiles}

Your job: find what they MISSED. Grep the ENTIRE app/ and resources/ for: every occurrence of type codes ('phone','messenger','price','address','social','text','phone_reserve'), every use of DataValue / value_type / ValueType, every hardcoded array/match/in_array enumerating types, and any helper/blade/component/route/console-command/API-controller that touches a data type. Cross-check against the covered touchpoints above. Report any file:location that handles a data type but is NOT in the covered set, and why it matters for adding social+address. Also return a deduped inventory of every hardcoded type-code list (file:line) — these are the highest-risk spots a new type silently falls out of.`, { label: 'critic:completeness', phase: 'Critic', schema: CRITIC_SCHEMA })

return { recon: recded, critic }
