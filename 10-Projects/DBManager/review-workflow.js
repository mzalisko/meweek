export const meta = {
  name: 'dbmanager-newtypes-review',
  description: 'Cross-model-style adversarial review of the new data-type changes (social/address/text) for correctness, security, regression',
  phases: [
    { title: 'Review', detail: 'three independent lenses find issues' },
    { title: 'Verify', detail: 'adversarially confirm each finding is real' },
  ],
}

const CORE = '10-Projects/DBManager/code/core'

const FINDINGS = {
  type: 'object', additionalProperties: false, required: ['lens', 'findings'],
  properties: {
    lens: { type: 'string' },
    findings: { type: 'array', items: { type: 'object', additionalProperties: false,
      required: ['title', 'file', 'location', 'severity', 'detail', 'suggested_fix'],
      properties: {
        title: { type: 'string' }, file: { type: 'string' }, location: { type: 'string' },
        severity: { type: 'string', enum: ['critical', 'high', 'medium', 'low'] },
        detail: { type: 'string' }, suggested_fix: { type: 'string' } } } },
  },
}

const VERDICT = {
  type: 'object', additionalProperties: false, required: ['title', 'is_real', 'confidence', 'reasoning'],
  properties: { title: { type: 'string' }, is_real: { type: 'boolean' }, confidence: { type: 'string', enum: ['high', 'medium', 'low'] }, reasoning: { type: 'string' }, corrected_severity: { type: 'string', enum: ['critical', 'high', 'medium', 'low', 'not-a-bug'] } },
}

const CONTEXT = `You are reviewing a CRITICAL change to DBManager (Laravel + Livewire). The change integrates two NEW data types — social (platform+handle+url) and address (STRUCTURED: country/region/city/street/postcode + a derived 'value' mirror string) — plus completes the 'text' type, across all mechanisms. DBManager has heightened security requirements.

The change is ALREADY COMMITTED. Review the CURRENT committed state of these files (read them in full; cwd is repo root, code under ${CORE}):
- ${CORE}/app/Livewire/ValueEditor.php — save() validation rules + content build for social/address; buildAddressContent(); edit()/createFor() load/reset of addr* props + originalValue; the type whitelist is now derived from ValueType::pluck('code') (replacing the old hardcoded create/edit in: lists).
- ${CORE}/app/Services/Publishing/SitePayloadCompiler.php — new socialItem()/addressItem() match arms; TYPE_ORDER additions; addressItem uses an explicit allow-list (no content-key leak).
- ${CORE}/app/Livewire/BulkOperations.php — apply() guard blocking replace_text/set_value when targetType==='address'; updatedTargetType() address default.
- ${CORE}/resources/views/livewire/value-editor.blade.php — social platform <select>, address structured fields, "Було:" original-value highlight.
- ${CORE}/resources/views/livewire/audit-manager.blade.php — $dataType arms for social/address.
Reference docs: ${CORE}/../ADDRESS_DECISION.md, NEW_TYPES_PLAN.md, DATA_TYPES_MAP.md.

Be adversarial and concrete. Only report REAL issues with file:line. Your return value is data.`

phase('Review')
const LENSES = [
  { key: 'correctness', prompt: `${CONTEXT}

LENS: CORRECTNESS. Hunt for real bugs: null/empty handling in buildAddressContent() (e.g. addr* are ?string=null — does '!== ""' behave right when null?); the value-mirror composition order/empties; EDIT round-trip for address (does saving an edited address preserve or silently drop fields? compare to messenger's only([...]) preserve block — address has none); the ValueType::pluck('code') whitelist (what if value_types table is empty or missing a code — does create break for ALL types? is the table guaranteed seeded in prod/migrations, not just tests?); social url derivation (messengerUrlFromValue returns null for non-http handles — is content['url'] then null acceptable?); the 'value required' exclusion list ['phone','price','address'] — does it wrongly require value for social/text in a bad way, or wrongly skip it; interaction of address with geoTags. Report concrete bugs.` },
  { key: 'security', prompt: `${CONTEXT}

LENS: SECURITY (DBManager is high-security). Check: does the new ValueType-derived whitelist allow creating/editing any unintended or privilege-relevant type (e.g. could a user mint an arbitrary type code)? Does addressItem/socialItem leak any sensitive/internal content keys into the PUBLIC site payload (verify the allow-list is exhaustive vs the generic default arm which spreads all keys)? Is address ungated-by-default a real exposure given it may hold PII — and is that consistent with how price (can_view_prices) is gated? Any mass-assignment risk via the new content arrays? Any XSS in the new blade (originalValue, address fields) — are they escaped? Any authorization bypass in the bulk address guard (can it be circumvented to still corrupt structured fields)?` },
  { key: 'regression', prompt: `${CONTEXT}

LENS: REGRESSION / INTEGRATION. Verify the change does NOT break the working types (phone/messenger/price) or other mechanisms: does deriving the whitelist from ValueType change behavior for existing create flows? Does the address bulk guard accidentally affect phone_reserve or other targetTypes? Does updatedTargetType()'s address branch conflict with the phone_reserve branch? Does excluding address from the generic 'value required' rule affect messenger/text? Does the new TYPE_ORDER reorder existing published payloads in a breaking way? Does $dataType arm addition affect phone/messenger/price audit labels? Cross-check against existing tests in ${CORE}/tests. Report real regressions only.` },
]

const reviews = await parallel(LENSES.map(l => () =>
  agent(l.prompt, { label: `review:${l.key}`, phase: 'Review', schema: FINDINGS, effort: 'high' })
    .then(r => ({ lens: l.key, result: r }))
))

const allFindings = reviews.filter(Boolean).flatMap(r => (r.result?.findings || []).map(f => ({ ...f, lens: r.lens })))
log(`Review: ${allFindings.length} raw findings across ${reviews.filter(Boolean).length} lenses`)

phase('Verify')
const verified = await parallel(allFindings.map(f => () =>
  agent(`${CONTEXT}

Adversarially VERIFY this finding. Read the actual code at the cited location and decide if it is a REAL issue or a false positive. Default to skepticism — many review findings are wrong.
FINDING: [${f.severity}] ${f.title}
FILE: ${f.file} @ ${f.location}
DETAIL: ${f.detail}
Confirm with the real code whether this is genuinely a bug/risk, and give a corrected severity.`, { label: `verify:${f.title.slice(0, 30)}`, phase: 'Verify', schema: VERDICT })
    .then(v => ({ finding: f, verdict: v }))
))

const confirmed = verified.filter(Boolean).filter(v => v.verdict?.is_real && v.verdict?.corrected_severity !== 'not-a-bug')
return { total_raw: allFindings.length, confirmed_count: confirmed.length, confirmed, all_verdicts: verified.filter(Boolean) }
