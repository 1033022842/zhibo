/* createCharacter v8 — two-gender, eight-screen character wizard.
 *
 * Entry: createCharacter.html loads either the legacy wizard (js/createCharacter.js,
 * ?legacy=1) or this file. Both are never started together.
 *
 * API contract (kept compatible with the existing Live.php):
 *   POST /api/live/customRoleCreate       Bearer, {code:'00000',data:{id,image_task_id}}
 *   GET  /api/live/customRoleImageStatus  ?persona_id=, {data:{status,image_url}}
 *   POST /api/live/customRoleRegenerate   {persona_id}
 *   POST /api/live/customRoleSave         {persona_id}
 *   auth failures: A0100 / A0101 / 10003 / 10004
 *
 * Guests never call the create API. "Bring her to life" is a local preview only;
 * the account gate uses the site's FyLogin modal with a same-origin redirect.
 */
(function () {
  'use strict'

  var VERSION = '8.0.0'
  var API = '/api/live'
  var TOKEN_KEY = 'live_access_token'
  var DRAFTS_KEY = 'cc8_drafts_v1'
  var GENDER_KEY = 'cc8_active_gender'
  var RESULT_KEY = 'cc8_result_v1'
  var MIGRATED_KEY = 'cc8_migrated_v2'
  var ASSET_MANIFEST = './assets/create/manifest.json?v=20261121v8c'
  var ASSET_ROOT = './assets/create/'

  var root = document.getElementById('cc8Root')
  if (!root) return

  /* ── options ─────────────────────────────────────────────────────── */

  var ETHNICITIES = [
    { v: 'white', l: 'White' },
    { v: 'asian', l: 'Asian' },
    { v: 'black', l: 'Black' },
    { v: 'latina', l: 'Latina' },
    { v: 'south-asian', l: 'South Asian' },
    { v: 'middle-eastern', l: 'Middle Eastern' }
  ]

  var OPTIONS = {
    female: {
      body: [
        { v: 'slim', l: 'Slim' }, { v: 'athletic', l: 'Athletic' },
        { v: 'average', l: 'Average' }, { v: 'curvy', l: 'Curvy' },
        { v: 'bbw', l: 'BBW' }
      ],
      breast: [
        { v: 'flat', l: 'Flat' }, { v: 'small', l: 'Small' },
        { v: 'average', l: 'Medium' }, { v: 'big', l: 'Large' },
        { v: 'huge', l: 'Extra Large' }
      ],
      butt: [
        { v: 'small', l: 'Small' }, { v: 'slim', l: 'Slim' },
        { v: 'athletic', l: 'Athletic' }, { v: 'big', l: 'Large' },
        { v: 'huge', l: 'Extra Large' }
      ],
      hairstyle: [
        { v: 'straight', l: 'Straight' }, { v: 'bangs', l: 'Bangs' },
        { v: 'braids', l: 'Braids' }, { v: 'curly', l: 'Curly' },
        { v: 'bun', l: 'Bun' }, { v: 'ponytail', l: 'Ponytail' },
        { v: 'bob', l: 'Bob' }, { v: 'bald', l: 'Bald' }
      ],
      color: [
        { v: 'brunette', l: 'Brunette' }, { v: 'blonde', l: 'Blonde' },
        { v: 'black', l: 'Black' }, { v: 'red', l: 'Red' },
        { v: 'pink', l: 'Pink' }, { v: 'white', l: 'White' },
        { v: 'blue', l: 'Blue' }, { v: 'green', l: 'Green' },
        { v: 'purple', l: 'Purple' }, { v: 'multicolor', l: 'Multicolor' }
      ],
      relationship: ['Girlfriend', 'Wife', 'Stranger', 'Friend', 'Sex Friend', 'School Mate', 'Work Colleague', 'Mistress', 'Step Sister', 'Step Mom', 'Landlord', 'Sugar Baby', 'Boss', 'Teacher', 'Student', 'Neighbour', 'Mother-In-Law', 'Sister-In-Law', 'Ex-Girlfriend', 'Best Friend'],
      occupation: ['Student', 'Dancer', 'Model', 'Maid', 'Cam Girl', 'CEO', 'Streamer', 'Bartender', 'Engineer', 'Lifeguard', 'Massage Therapist', 'Teacher', 'Nurse', 'Secretary', 'Yoga Instructor', 'Fitness Coach', 'Chef', 'Artist', 'Movie Star', 'Doctor', 'Librarian', 'Spy', 'Police Officer', 'Lawyer', 'Hairdresser', 'Dentist', 'Singer', 'Writer', 'Flight Attendant', 'Athlete', 'Scientist', 'Florist', 'Makeup Artist', 'Photographer', 'Designer', 'Pharmacist'],
      personality: ['Nympho', 'Lover', 'Submissive', 'Dominant', 'Temptress', 'Innocent', 'Caregiver', 'Experimenter', 'Mean', 'Confidant', 'Shy', 'Queen', 'Playful', 'Romantic', 'Mysterious', 'Devoted'],
      preferences: ['Bondage', 'Spanking', 'Collar & Leash', 'Punishment', 'Humiliation', 'Public Play', 'Roleplay', 'Anal Play', 'Oral Play', 'Dirty Talk', 'Breeding', 'Edging', 'Obedience', 'Control', 'Playful Teasing', 'Cuddling', 'Slow & Sensual', 'Hair Pulling', 'Blindfold', 'Massage']
    },
    male: {
      body: [
        { v: 'slim', l: 'Slim' }, { v: 'average', l: 'Average' },
        { v: 'muscular', l: 'Muscular' }, { v: 'stout', l: 'Stout' },
        { v: 'large', l: 'Large' }
      ],
      chest: [
        { v: 'slim', l: 'Slim' }, { v: 'toned', l: 'Toned' },
        { v: 'muscular', l: 'Muscular' }, { v: 'broad', l: 'Broad' }
      ],
      butt: [
        { v: 'small', l: 'Small' }, { v: 'slim', l: 'Slim' },
        { v: 'athletic', l: 'Athletic' }, { v: 'big', l: 'Large' },
        { v: 'huge', l: 'Extra Large' }
      ],
      hairstyle: [
        { v: 'buzz-cut', l: 'Buzz Cut' }, { v: 'fade', l: 'Fade' },
        { v: 'undercut', l: 'Undercut' }, { v: 'bro-flow', l: 'Bro Flow' },
        { v: 'long', l: 'Long' }, { v: 'shaggy', l: 'Shaggy' },
        { v: 'mullet', l: 'Mullet' }, { v: 'bald', l: 'Bald' }
      ],
      color: [
        { v: 'brunette', l: 'Brunette' }, { v: 'blonde', l: 'Blonde' },
        { v: 'black', l: 'Black' }, { v: 'red', l: 'Red' },
        { v: 'white', l: 'White' }, { v: 'blue', l: 'Blue' },
        { v: 'green', l: 'Green' }, { v: 'purple', l: 'Purple' },
        { v: 'multicolor', l: 'Multicolor' }
      ],
      relationship: ['Boyfriend', 'Husband', 'Stranger', 'Friend', 'Fling', 'Work Colleague', 'Boss', 'Roommate', 'Ex-Boyfriend', 'Neighbour', 'Step Brother', 'Teacher', 'Sugar Daddy', 'Best Friend', 'Bodyguard', 'Trainer'],
      occupation: ['Engineer', 'Doctor', 'CEO', 'Personal Trainer', 'Chef', 'Artist', 'Lawyer', 'Pilot', 'Firefighter', 'Musician', 'Photographer', 'Teacher', 'Scientist', 'Writer', 'Model', 'Streamer', 'Bartender', 'Soldier', 'Architect', 'Vet', 'Bodyguard', 'Mechanic', 'Professor', 'Actor'],
      personality: ['Protective', 'Charming', 'Dominant', 'Playful', 'Romantic', 'Stoic', 'Shy', 'Confident', 'Caring', 'Mysterious', 'Teasing', 'Devoted', 'Lover', 'Experimenter'],
      preferences: ['Bondage', 'Spanking', 'Collar & Leash', 'Punishment', 'Humiliation', 'Public Play', 'Roleplay', 'Anal Play', 'Oral Play', 'Dirty Talk', 'Edging', 'Obedience', 'Control', 'Playful Teasing', 'Cuddling', 'Slow & Sensual', 'Hair Pulling', 'Blindfold', 'Massage', 'Praise']
    }
  }

  /* 关系/职业/个性表用纯字符串维护；统一成 {v,l}，v 即落库文案。 */
  ;['female', 'male'].forEach(function (g) {
    ;['relationship', 'occupation', 'personality'].forEach(function (k) {
      OPTIONS[g][k] = (OPTIONS[g][k] || []).map(function (x) {
        return typeof x === 'string' ? { v: x, l: x } : x
      })
    })
  })

  var AGE_RANGES = [
    { v: '18-24', l: '18–24', age: 22 },
    { v: '25-34', l: '25–34', age: 29 },
    { v: '35-44', l: '35–44', age: 39 },
    { v: '45+', l: '45+', age: 48 }
  ]

  var COLORS = {
    brunette: '#6b4a36', blonde: '#d9b56d', black: '#1b1b1b', red: '#a83a2a',
    pink: '#e77fa4', white: '#e8e8e8', blue: '#4a6fb5', green: '#4f8f6a',
    purple: '#7b5aa6', multicolor: 'linear-gradient(135deg,#e77fa4,#4a6fb5 45%,#4f8f6a 75%,#d9b56d)'
  }

  var NAME_POOL = {
    female: ['Luna', 'Aria', 'Mia', 'Chloe', 'Zoe', 'Ruby', 'Ivy', 'Stella', 'Nova', 'Bella', 'Zara', 'Lily', 'Rose', 'Daisy', 'Willow', 'Sienna', 'Skylar', 'Aurora', 'Jade', 'Nora', 'Riley', 'Sasha', 'Tessa', 'Violet', 'Yara', 'Aiko', 'Mei', 'Sakura', 'Camila', 'Lucia', 'Sofia', 'Valentina', 'Amara', 'Zahra', 'Layla', 'Nadia', 'Aisha', 'Priya', 'Elena', 'Mila', 'Anya', 'Freya'],
    male: ['Leo', 'Kai', 'Ethan', 'Noah', 'Liam', 'Lucas', 'Mateo', 'Adrian', 'Julian', 'Ezra', 'Miles', 'Owen', 'Theo', 'Roman', 'Damien', 'Asher', 'Silas', 'Jude', 'Nico', 'Rafael', 'Diego', 'Andre', 'Malik', 'Idris', 'Omar', 'Ravi', 'Arjun', 'Kenji', 'Ren', 'Hiro', 'Dmitri', 'Viktor', 'Elias', 'Marcus', 'Dante', 'Sebastian', 'Felix', 'Aiden', 'Rowan', 'Cassian']
  }

  var BUTT_LABEL = { small: 'Small', slim: 'Slim', athletic: 'Athletic', big: 'Big', huge: 'Extra Large', medium: 'Average', large: 'Big', xl: 'Extra Large' }
  var ETH_LABEL = {
    white: 'White', asian: 'Asian', black: 'Black', latina: 'Latina',
    'south-asian': 'South Asian', 'middle-eastern': 'Middle Eastern'
  }
  var ETH_BACKEND = {
    white: 'Caucasian', asian: 'Asian', black: 'Black / Afro', latina: 'Latina',
    'south-asian': 'South Asian', 'middle-eastern': 'Middle Eastern'
  }

  /* Legacy flat assets. Deliberately limited:
   *  - base body/breast/hair photos are only valid for the White female set;
   *  - the old Arab card is excluded because its skin tone is the one being replaced;
   *  - anime has no Black / South Asian / Middle Eastern cards at all.
   * Anything else resolves through manifest.json or a neutral "Art pending" card. */
  var LEGACY_ETH = {
    white: { Realistic: 'eth-r-caucasian.webp', Anime: 'eth-a-caucasian.webp' },
    asian: { Realistic: 'eth-r-asian.webp', Anime: 'eth-a-asian.webp' },
    black: { Realistic: 'eth-r-blackafro.webp', Anime: '' },
    latina: { Realistic: 'eth-r-latina.webp', Anime: 'eth-a-latina.webp' },
    'south-asian': { Realistic: '', Anime: '' },
    'middle-eastern': { Realistic: '', Anime: '' }
  }
  var LEGACY_BODY = {
    Realistic: { slim: 'body-r-skinny.webp', athletic: 'body-r-athletic.webp', average: 'body-r-average.webp', curvy: 'body-r-curvy.webp', bbw: 'body-r-bbw.webp' },
    Anime: { slim: 'body-a-skinny.webp', athletic: 'body-a-athletic.webp', average: 'body-a-average.webp', curvy: 'body-a-curvy.webp', bbw: '' }
  }
  var LEGACY_BREAST = {
    Realistic: { flat: '', small: 'breast-r-small.webp', average: 'breast-r-medium.webp', big: 'breast-r-large.webp', huge: 'breast-r-xl.webp' },
    Anime: { flat: '', small: 'breast-a-small.webp', average: 'breast-a-medium.webp', big: 'breast-a-large.webp', huge: 'breast-a-xl.webp' }
  }
  var LEGACY_HAIR = {
    Realistic: { straight: 'hair-r-straight.webp', bangs: 'hair-r-bangs.webp', curly: 'hair-r-curly.webp', bun: 'hair-r-bun.webp', bob: '', braids: '', bald: '', ponytail: 'hair-r-ponytail.webp' },
    Anime: { straight: 'hair-a-straight.webp', bangs: 'hair-a-bangs.webp', curly: 'hair-a-curly.webp', bun: 'hair-a-bun.webp', bob: '', braids: '', bald: '', ponytail: 'hair-a-ponytail.webp' }
  }

  /* ── state ────────────────────────────────────────────────────────── */

  function blankDraft(gender) {
    return {
      version: 8,
      gender: gender,
      style: '',
      ethnicity: '',
      body: '',
      breast: '',
      chest: '',
      butt: '',
      hairstyle: '',
      color: '',
      name: '',
      ageRange: '',
      relationship: '',
      occupation: '',
      personality: '',
      preferences: [],
      appearance_description: '',
      personality_description: '',
      promptMode: false,
      step: 1,
      voice: gender === 'female' ? 'sweet' : '',
      clothing: gender === 'female' ? 'Bikini' : ''
    }
  }

  var state = {
    drafts: { female: blankDraft('female'), male: blankDraft('male') },
    gender: 'female',
    screen: 1,
    manifest: null,
    manifestIndex: null,
    missing: {},
    modal: null,
    prompt: false,
    gen: null,
    submitting: false,
    created: null,
    pollTimer: null,
    pollBusy: false,
    genTimer: null,
    lastScreen: 0,
    lastFocus: null
  }

  function draft() { return state.drafts[state.gender] }
  function $(sel, ctx) { return (ctx || document).querySelector(sel) }
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)) }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    })
  }
  function optList(group) {
    var g = OPTIONS[state.gender] || OPTIONS.female
    /* relationship/occupation/personality are plain string lists */
    return (g[group] || []).map(function (o) {
      return typeof o === 'string' ? { v: o, l: o } : o
    })
  }
  function labelOf(group, value) {
    if (group === 'ethnicity') return ETH_LABEL[value] || value || ''
    var list = optList(group)
    for (var i = 0; i < list.length; i++) if (list[i].v === value) return list[i].l
    return value || ''
  }
  function ageLabel(v) {
    for (var i = 0; i < AGE_RANGES.length; i++) if (AGE_RANGES[i].v === v) return AGE_RANGES[i].l
    return v || ''
  }
  function ageInt(v) {
    for (var i = 0; i < AGE_RANGES.length; i++) if (AGE_RANGES[i].v === v) return AGE_RANGES[i].age
    return 22
  }
  function pronoun() { return state.gender === 'male' ? 'he' : 'she' }
  function possessive() { return state.gender === 'male' ? 'his' : 'her' }
  function objectPronoun() { return state.gender === 'male' ? 'him' : 'her' }
  function partnerWord() { return state.gender === 'male' ? 'AI boyfriend' : 'AI girlfriend' }
  function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s }

  /* ── persistence ──────────────────────────────────────────────────── */

  function cleanText(v, max) {
    return String(v == null ? '' : v)
      .replace(/[\u0000-\u001f\u007f]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, max || 120)
  }
  function cleanMultiline(v, max) {
    return String(v == null ? '' : v)
      .replace(/\r\n?/g, '\n')
      .replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g, '')
      .slice(0, max || 1000)
  }
  function hasOption(group, value) {
    if (!value) return false
    var list = optList(group)
    for (var i = 0; i < list.length; i++) if (list[i].v === value) return true
    return false
  }
  function optionFromLabel(group, raw) {
    var want = cleanText(raw, 80).toLowerCase()
    if (!want) return ''
    var list = optList(group)
    for (var i = 0; i < list.length; i++) {
      if (String(list[i].v).toLowerCase() === want || String(list[i].l).toLowerCase() === want) return list[i].v
    }
    return ''
  }
  function validAgeRange(v) {
    for (var i = 0; i < AGE_RANGES.length; i++) if (AGE_RANGES[i].v === v) return true
    return false
  }

  var DRAFT_LIMITS = { name: 32, appearance_description: 1000, personality_description: 1000 }

  function saveDrafts() {
    try {
      localStorage.setItem(DRAFTS_KEY, JSON.stringify({
        version: 8,
        activeGender: state.gender,
        drafts: state.drafts
      }))
      localStorage.setItem(GENDER_KEY, state.gender)
    } catch (e) { /* storage unavailable */ }
  }
  function saveResult(result) {
    try {
      if (result) localStorage.setItem(RESULT_KEY, JSON.stringify(result))
      else localStorage.removeItem(RESULT_KEY)
    } catch (e) { /* ignore */ }
  }
  function loadResult() {
    try { return JSON.parse(localStorage.getItem(RESULT_KEY) || 'null') } catch (e) { return null }
  }
  function normalizeDraft(raw, gender) {
    var d = blankDraft(gender)
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return d
    for (var k in d) {
      if (!Object.prototype.hasOwnProperty.call(d, k)) continue
      if (raw[k] === undefined || raw[k] === null) continue
      if (k === 'version' || k === 'gender') continue
      if (k === 'preferences') {
        var picked = []
        if (Array.isArray(raw[k])) {
          raw[k].forEach(function (x) {
            var v = optionFromLabel('preferences', x)
            if (v && picked.indexOf(v) < 0 && picked.length < 5) picked.push(v)
          })
        }
        d[k] = picked
      } else if (k === 'step') {
        var n = parseInt(raw[k], 10)
        d[k] = n >= 1 && n <= 8 ? n : 1
      } else if (k === 'promptMode') {
        d[k] = raw[k] === true
      } else if (k === 'ageRange') {
        d[k] = validAgeRange(raw[k]) ? raw[k] : ''
      } else if (k === 'style') {
        d[k] = raw[k] === 'Realistic' || raw[k] === 'Anime' ? raw[k] : ''
      } else if (k === 'ethnicity') {
        d[k] = Object.prototype.hasOwnProperty.call(ETH_LABEL, raw[k]) ? raw[k] : ''
      } else if (k === 'name') {
        d[k] = cleanText(raw[k], DRAFT_LIMITS.name)
      } else if (k === 'appearance_description' || k === 'personality_description') {
        d[k] = cleanMultiline(raw[k], DRAFT_LIMITS[k])
      } else if (k === 'voice' || k === 'clothing') {
        d[k] = cleanText(raw[k], 24) || d[k]
      } else if (hasOption(k, raw[k])) {
        d[k] = raw[k]
      } else if (k === 'relationship' || k === 'occupation' || k === 'personality') {
        d[k] = optionFromLabel(k, raw[k])
      }
    }
    d.version = 8
    d.gender = gender
    if (d.promptMode !== true) d.promptMode = false
    if (d.preferences.length > 5) d.preferences = d.preferences.slice(0, 5)
    return d
  }
  function loadState() {
    var saved = null
    try { saved = JSON.parse(localStorage.getItem(DRAFTS_KEY) || 'null') } catch (e) { saved = null }
    var source = null
    if (saved && saved.drafts && typeof saved.drafts === 'object') {
      source = saved.drafts
      if (saved.activeGender === 'male' || saved.activeGender === 'female') state.gender = saved.activeGender
    } else if (saved && (saved.female || saved.male)) {
      /* legacy flat shape written by the first v8 build */
      source = saved
    }
    if (source) {
      state.drafts.female = normalizeDraft(source.female, 'female')
      state.drafts.male = normalizeDraft(source.male, 'male')
    } else if (migrateLegacy()) {
      /* migration must be durable before the marker is trusted */
      saveDrafts()
    }
    var g = localStorage.getItem(GENDER_KEY)
    if (g === 'male' || g === 'female') state.gender = g
    state.screen = Math.min(8, Math.max(1, draft().step || 1))
  }
  function migrateLegacy() {
    if (localStorage.getItem(MIGRATED_KEY)) return false
    var oldProfile = null, oldStep = 0
    try {
      oldProfile = JSON.parse(localStorage.getItem('cc_profile_v2') || 'null')
      oldStep = parseInt(localStorage.getItem('cc_step_v2') || '0', 10) || 0
    } catch (e) { return false }
    if (!oldProfile || typeof oldProfile !== 'object') {
      try { localStorage.setItem(MIGRATED_KEY, '1') } catch (e) {}
      return false
    }
    var raceMap = { 'Caucasian': 'white', 'White': 'white', 'Asian': 'asian', 'Black / Afro': 'black', 'Black': 'black', 'Latina': 'latina', 'South Asian': 'south-asian', 'Arab': 'middle-eastern', 'Middle Eastern': 'middle-eastern' }
    var bodyMap = { 'Skinny': 'slim', 'Slim': 'slim', 'Athletic': 'athletic', 'Average': 'average', 'Curvy': 'curvy', 'BBW': 'bbw', 'Muscular': 'muscular', 'Dad Bod': 'dadbod' }
    var breastMap = { 'Flat': 'flat', 'Small': 'small', 'Medium': 'average', 'Large': 'big', 'Extra Large': 'huge', 'XL': 'huge' }
    var d = state.drafts.female
    d.style = oldProfile.category === 'Anime' ? 'Anime' : (oldProfile.category === 'Realistic' ? 'Realistic' : '')
    d.ethnicity = raceMap[cleanText(oldProfile.race, 40)] || ''
    d.body = bodyMap[cleanText(oldProfile.body, 40)] || optionFromLabel('body', oldProfile.body)
    d.breast = breastMap[cleanText(oldProfile.breast, 40)] || optionFromLabel('breast', oldProfile.breast)
    d.hairstyle = optionFromLabel('hairstyle', oldProfile.hairstyle)
    d.color = optionFromLabel('color', oldProfile.hair)
    d.name = cleanText(oldProfile.name, 32)
    d.relationship = optionFromLabel('relationship', oldProfile.relation)
    d.occupation = optionFromLabel('occupation', oldProfile.profession)
    d.personality = optionFromLabel('personality', oldProfile.personality)
    d.preferences = []
    if (Array.isArray(oldProfile.kinks)) {
      oldProfile.kinks.forEach(function (x) {
        var v = optionFromLabel('preferences', x)
        if (v && d.preferences.indexOf(v) < 0 && d.preferences.length < 5) d.preferences.push(v)
      })
    }
    if (oldProfile.age) {
      var n = parseInt(oldProfile.age, 10) || 0
      d.ageRange = n >= 45 ? '45+' : n >= 35 ? '35-44' : n >= 25 ? '25-34' : '18-24'
    }
    /* Legacy wizard: 1 race, 2 hair/eyes, 3 body, 4 final form.
       v8: 6 personality, 7 preferences, 8 summary — never reuse the old 7th index. */
    var stepMap = { 0: 1, 1: 2, 2: 4, 3: 3, 4: 5, 5: 6, 6: 7, 7: 8 }
    d.step = stepMap[oldStep] || 1
    state.screen = d.step
    try { localStorage.setItem(MIGRATED_KEY, '1') } catch (e) {}
    return true
  }

  /* ── assets / manifest ────────────────────────────────────────────── */

  function externalAsset(p) {
    return /^(https?:)?\/\//i.test(p || '') || /^data:/i.test(p || '')
  }
  function assetUrl(p) {
    if (!p || externalAsset(p)) return ''
    p = String(p).replace(/^\.\//, '')
    if (p.indexOf('assets/') === 0) return './' + p
    if (p.charAt(0) === '/') return p
    return ASSET_ROOT + p
  }
  function manifestLookup(gender, style, group, option, ethnicity) {
    var m = state.manifest
    if (!m || !m.assets) return ''
    var groups = [group]
    if (group === 'breast' && gender === 'male') groups.unshift('chest')
    if (group === 'chest') groups.push('breast')
    if (group === 'ethnicity') groups.push('eth')
    if (group === 'hairstyle') groups.push('hair')
    if (group === 'color') groups.push('hairColor')
    var keys = []
    groups.forEach(function (g) {
      /* Ethnicity cards are keyed by the option being shown, not by the
         currently-selected ethnicity. */
      var ethScope = g === 'ethnicity' ? option : ethnicity
      /* Only ethnicity-scoped keys are trusted for appearance assets. A manifest
         entry without ethnicity would silently put the White set on everyone. */
      if (ethScope) {
        keys.push([gender, style, ethScope, g, option].join('|'))
        keys.push([gender, ethScope, g, option].join('|'))
        keys.push([gender, style, ethScope, g, option, 'default'].join('|'))
      }
      if (g === 'entry') keys.push([gender, style, 'entry'].join('|'))
    })
    for (var i = 0; i < keys.length; i++) {
      /* manifest writers use both `Anime` and `anime`; normalise the lookup */
      var hit = m.assets[keys[i]] || (state.manifestIndex && state.manifestIndex[keys[i].toLowerCase()])
      if (hit) {
        var p = typeof hit === 'string' ? hit : hit.path
        var url = assetUrl(p)
        if (url) return url
      }
    }
    return ''
  }
  function recordMissing(key) {
    state.missing[key] = 1
  }
  function legacyAsset(group, option) {
    if (state.gender !== 'female') return ''
    var d = draft()
    var style = d.style
    if (!style) return ''
    if (group === 'ethnicity') {
      var e = LEGACY_ETH[option]
      return (e && e[style]) || ''
    }
    /* The flat body/breast/hair photos depict the White female set only.
       Read the draft, never a stale global, and never cross ethnicity. */
    if (d.ethnicity !== 'white') return ''
    if (group === 'body') return (LEGACY_BODY[style] || {})[option] || ''
    if (group === 'breast') return (LEGACY_BREAST[style] || {})[option] || ''
    if (group === 'hairstyle') return (LEGACY_HAIR[style] || {})[option] || ''
    return ''
  }
  function assetFor(group, option) {
    if (!option) return ''
    var d = draft()
    var style = d.style
    if (!style) return ''
    var url = manifestLookup(state.gender, style, group, option, d.ethnicity)
    if (url) return url
    var legacy = legacyAsset(group, option)
    if (legacy) return ASSET_ROOT + legacy
    recordMissing([state.gender, style, d.ethnicity, group, option].join('|'))
    return ''
  }
  function entryAsset(style) {
    if (!style) return ''
    var m = state.manifest
    if (m && m.entry && m.entry[state.gender] && m.entry[state.gender][style]) {
      var url = assetUrl(m.entry[state.gender][style])
      if (url) return url
    }
    if (state.gender === 'female') {
      return ASSET_ROOT + (style === 'Anime' ? 'entry-anime.webp' : 'entry-realistic.webp')
    }
    recordMissing([state.gender, style, 'entry', style, ''].join('|'))
    return ''
  }
  function loadManifest() {
    return fetch(ASSET_MANIFEST, { cache: 'no-store' }).then(function (r) {
      if (!r.ok) return null
      return r.json()
    }).then(function (m) {
      if (m && typeof m === 'object') {
        state.manifest = m
        state.manifestIndex = {}
        Object.keys(m.assets || {}).forEach(function (k) {
          state.manifestIndex[k.toLowerCase()] = m.assets[k]
        })
        state.missing = {}
      }
    }).catch(function () { state.manifest = null })
  }

  function placeholderHtml(title, kind) {
    var icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.4"/><path d="M5.4 20c.6-3.7 3.1-6 6.6-6s6 2.3 6.6 6"/></svg>'
    if (kind === 'hair') icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20c-1.8-2.4-2.4-5.1-2.4-8.1C3.6 6.9 7.3 4 12 4s8.4 2.9 8.4 7.9c0 3-.6 5.7-2.4 8.1"/><path d="M8 20c-.8-2.4-.4-5.2.9-7.2"/><path d="M16 20c.8-2.4.4-5.2-.9-7.2"/></svg>'
    return '<span class="cc8-ph" aria-label="Art pending">' + icon + '<b>Art pending</b></span>'
  }

  /* ── render shell ─────────────────────────────────────────────────── */

  var HEADER_TITLE = { female: 'Create your AI girlfriend', male: 'Create your AI boyfriend' }
  var SCREEN_TITLE = {
    1: 'Create your Dream AI Girl', 2: 'Ethnicity', 3: 'Body & figure',
    4: 'Hair & style', 5: 'Name & age', 6: 'Adjust her personality',
    7: 'Preferences', 8: 'Your selection'
  }

  root.innerHTML = '' +
    '<header class="cc8-top">' +
      '<button class="cc8-iconbtn" id="cc8Back" type="button" data-act="back" aria-label="Go back">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>' +
      '</button>' +
      '<h1 class="cc8-top-title" id="cc8Title">' + HEADER_TITLE.female + '</h1>' +
      '<button class="cc8-iconbtn cc8-x" id="cc8Close" type="button" data-act="close" aria-label="Close">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>' +
      '</button>' +
    '</header>' +
    '<div class="cc8-progress" id="cc8Progress" aria-hidden="true"></div>' +
    '<main class="cc8-scroll" id="cc8Scroll"><div class="cc8-inner" id="cc8Inner"></div></main>' +
    '<div class="cc8-bottom" id="cc8Bottom"></div>' +
    '<div id="cc8Layer"></div>'

  var els = {
    scroll: document.getElementById('cc8Scroll'),
    inner: document.getElementById('cc8Inner'),
    bottom: document.getElementById('cc8Bottom'),
    layer: document.getElementById('cc8Layer'),
    title: document.getElementById('cc8Title'),
    back: document.getElementById('cc8Back'),
    progress: document.getElementById('cc8Progress')
  }

  function renderProgress() {
    var html = ''
    for (var i = 1; i <= 8; i++) html += '<i class="' + (i <= state.screen ? 'on' : '') + '"></i>'
    els.progress.innerHTML = html
  }

  function renderHeader() {
    var d = draft()
    els.title.textContent = HEADER_TITLE[state.gender]
    els.back.disabled = state.screen === 1 && !state.prompt
    document.documentElement.setAttribute('data-cc8-gender', state.gender)
  }

  function render() {
    renderHeader()
    renderProgress()
    var d = draft()
    d.step = state.screen
    saveDrafts()
    var scrollTop = els.scroll.scrollTop
    if (state.lastScreen !== state.screen) scrollTop = 0
    var html = ''
    if (state.screen === 1) html = screenEntry()
    else if (state.screen === 2) html = screenEthnicity()
    else if (state.screen === 3) html = screenBody()
    else if (state.screen === 4) html = screenHair()
    else if (state.screen === 5) html = screenName()
    else if (state.screen === 6) html = screenPersonality()
    else if (state.screen === 7) html = screenPreferences()
    else html = screenSummary()
    els.inner.innerHTML = html
    renderBottom()
    state.lastScreen = state.screen
    els.scroll.scrollTop = scrollTop
    root.setAttribute('data-cc8-screen', String(state.screen))
    root.setAttribute('data-cc8-gender', state.gender)
    root.setAttribute('data-cc8-ready', '1')
    updateCtaState()
    if (state.screen === 4) {
      var on = $('.cc8-swatch.on')
      if (on) try { on.scrollIntoView({ block: 'nearest', inline: 'center' }) } catch (e) { /* ignore */ }
    }
  }

  function renderBottom() {
    var label = state.screen === 1 ? 'Start' : state.screen === 8 ? (state.gender === 'male' ? 'Bring him to life' : 'Bring her to life') : 'Next'
    var wide = state.screen === 8 ? ' w280' : ''
    els.bottom.innerHTML = '<button class="cc8-cta' + wide + '" id="cc8Cta" type="button" data-act="cta">' + esc(label) + '</button>'
    updateCtaState()
  }

  function ctaBlocked() {
    if (state.screen === 1) return !draft().style
    if (state.screen === 2) return !draft().ethnicity
    if (state.screen === 5) return !String(draft().name || '').trim()
    return false
  }
  function updateCtaState() {
    var btn = document.getElementById('cc8Cta')
    if (!btn) return
    btn.disabled = ctaBlocked()
  }

  /* ── screen 1: style + gender ─────────────────────────────────────── */

  function genderIcon(g) {
    if (g === 'male') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="10" cy="14" r="5"/><path d="M14.5 9.5L20 4M15 4h5v5"/></svg>'
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="9" r="5"/><path d="M12 14v7M8.5 18h7"/></svg>'
  }

  function screenEntry() {
    var d = draft()
    var title = state.gender === 'male' ? 'Create your Dream <em>AI Boy</em>' : 'Create your Dream <em>AI Girl</em>'
    var cards = ['Realistic', 'Anime'].map(function (style) {
      var src = entryAsset(style)
      var body = src
        ? '<img src="' + esc(src) + '" alt="' + esc(style) + ' ' + esc(state.gender) + '" draggable="false">'
        : placeholderHtml(style + ' ' + (state.gender === 'male' ? 'male' : 'female'), 'entry')
      return '<button class="cc8-entry-card' + (d.style === style ? ' on' : '') + '" type="button" data-act="style" data-style="' + style + '" aria-pressed="' + (d.style === style) + '">' +
        body + '<span class="cc8-entry-label">' + style + '</span></button>'
    }).join('')
    return '<section class="cc8-step cc8-entry" data-cc8-screen-body data-screen="1">' +
      '<h2 class="cc8-hero">' + title + '</h2>' +
      '<div class="cc8-gender" role="group" aria-label="Character gender">' +
        '<button type="button" data-act="gender" data-gender="female" aria-pressed="' + (state.gender === 'female') + '">' + genderIcon('female') + 'Female</button>' +
        '<button type="button" data-act="gender" data-gender="male" aria-pressed="' + (state.gender === 'male') + '">' + genderIcon('male') + 'Male</button>' +
      '</div>' +
      '<div class="cc8-entry-grid">' + cards + '</div>' +
      '<button class="cc8-linkbtn" type="button" data-act="prompt">Write a prompt instead</button>' +
      '</section>'
  }

  /* ── screen 2: ethnicity ──────────────────────────────────────────── */

  function optionCard(group, o, act) {
    var d = draft()
    var on = d[group] === o.v
    var img = assetFor(group, o.v)
    var inner = img
      ? '<img src="' + esc(img) + '" alt="' + esc(o.l) + '" loading="lazy" draggable="false">'
      : placeholderHtml(o.l, group === 'hairstyle' ? 'hair' : 'eth')
    return '<button class="cc8-opt' + (on ? ' on' : '') + '" type="button" data-act="' + (act || 'pick') + '" data-group="' + group + '" data-value="' + esc(o.v) + '" aria-pressed="' + on + '">' +
      inner + '<span class="cc8-opt-label">' + esc(o.l) + '</span></button>'
  }

  function screenEthnicity() {
    var cards = ETHNICITIES.map(function (o) { return optionCard('ethnicity', o) }).join('')
    return '<section class="cc8-step" data-cc8-screen-body data-screen="2">' +
      '<h2 class="cc8-h2">Ethnicity</h2>' +
      '<p class="cc8-sub">Choose ' + possessive() + ' heritage</p>' +
      '<div class="cc8-grid3">' + cards + '</div>' +
      '</section>'
  }

  /* ── screen 3: body ───────────────────────────────────────────────── */

  function groupAvailable(group) {
    /* The reference male wizard has no butt step; assets mark male|butt as
       not-in-reference. Hide instead of showing a permanent placeholder. */
    if (group === 'butt' && state.gender === 'male') return false
    return true
  }
  function screenBody() {
    var groups = (state.gender === 'male'
      ? [{ k: 'body', t: 'Body type' }, { k: 'chest', t: 'Chest' }, { k: 'butt', t: 'Butt size' }]
      : [{ k: 'body', t: 'Body type' }, { k: 'breast', t: 'Breast size' }, { k: 'butt', t: 'Butt size' }]).filter(function (g) { return groupAvailable(g.k) })
    var html = groups.map(function (g) {
      var cards = optList(g.k).map(function (o) { return optionCard(g.k, o) }).join('')
      return '<div class="cc8-group"><h3 class="cc8-group-title">' + g.t + '<small>tap to choose</small></h3><div class="cc8-row">' + cards + '</div></div>'
    }).join('')
    return '<section class="cc8-step" data-cc8-screen-body data-screen="3">' +
      '<h2 class="cc8-h2">Body &amp; figure</h2>' +
      '<p class="cc8-sub">Build ' + objectPronoun() + ' the way you like</p>' + html + '</section>'
  }

  /* ── screen 4: hair ───────────────────────────────────────────────── */

  function swatchHtml(o) {
    var d = draft()
    var on = d.color === o.v
    var bg = COLORS[o.v] || '#333'
    var style = bg.indexOf('gradient') >= 0 ? 'background-image:' + bg : 'background:' + bg
    return '<button class="cc8-swatch' + (on ? ' on' : '') + '" type="button" data-act="pick" data-group="color" data-value="' + esc(o.v) + '" aria-pressed="' + on + '">' +
      '<i style="' + style + '"></i>' + esc(o.l) + '</button>'
  }

  function screenHair() {
    var colors = optList('color').map(swatchHtml).join('')
    var styles = optList('hairstyle').map(function (o) { return optionCard('hairstyle', o) }).join('')
    return '<section class="cc8-step" data-cc8-screen-body data-screen="4">' +
      '<h2 class="cc8-h2">Hair &amp; style</h2>' +
      '<p class="cc8-sub">Pick a color and a cut</p>' +
      '<div class="cc8-group"><h3 class="cc8-group-title">Hair color</h3><div class="cc8-swatches">' + colors + '</div></div>' +
      '<div class="cc8-group"><h3 class="cc8-group-title">Hairstyle</h3><div class="cc8-grid3">' + styles + '</div></div>' +
      '</section>'
  }

  /* ── screen 5: name + age ─────────────────────────────────────────── */

  function screenName() {
    var d = draft()
    var ages = AGE_RANGES.map(function (a) {
      return '<button class="cc8-pill' + (d.ageRange === a.v ? ' on' : '') + '" type="button" data-act="pick" data-group="ageRange" data-value="' + a.v + '" aria-pressed="' + (d.ageRange === a.v) + '">' + a.l + '</button>'
    }).join('')
    return '<section class="cc8-step" data-cc8-screen-body data-screen="5">' +
      '<h2 class="cc8-h2">Name &amp; age</h2>' +
      '<p class="cc8-sub">What should ' + pronoun() + ' be called?</p>' +
      '<div class="cc8-field"><input id="cc8Name" data-input="name" type="text" maxlength="32" autocomplete="off" placeholder="' + (state.gender === 'male' ? 'His name' : 'Her name') + '" value="' + esc(d.name) + '">' +
        '<button type="button" data-act="random-name" aria-label="Random name" title="Surprise me">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="8.5" cy="8.5" r="1.3" fill="currentColor" stroke="none"/><circle cx="15.5" cy="15.5" r="1.3" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.3" fill="currentColor" stroke="none"/></svg>' +
        '</button></div>' +
      '<h3 class="cc8-group-title" style="justify-content:center;padding:0 16px">Age range</h3>' +
      '<div class="cc8-pills">' + ages + '</div>' +
      '</section>'
  }

  /* ── screen 6: personality ────────────────────────────────────────── */

  var ROW_DEFS = [
    { k: 'relationship', t: 'Relationship to you' },
    { k: 'occupation', t: 'Occupation' },
    { k: 'personality', t: 'Personality' }
  ]

  function screenPersonality() {
    var d = draft()
    var rows = ROW_DEFS.map(function (r) {
      var val = d[r.k] ? labelOf(r.k, d[r.k]) : 'Surprise me'
      return '<div class="cc8-arow"><div class="cc8-arow-label">' + r.t + '</div>' +
        '<button class="cc8-arow-btn" type="button" data-act="open-picker" data-field="' + r.k + '">' +
          '<span>' + esc(val) + '</span>' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>' +
        '</button></div>'
    }).join('')
    return '<section class="cc8-step" data-cc8-screen-body data-screen="6">' +
      '<h2 class="cc8-h2">Adjust ' + possessive() + ' personality</h2>' +
      '<p class="cc8-sub">Leave any row on Surprise me and we will choose for you</p>' +
      '<div class="cc8-rows">' + rows + '</div></section>'
  }

  /* ── screen 7: preferences ────────────────────────────────────────── */

  function screenPreferences() {
    var d = draft()
    var chips = optList('preferences').map(function (o) {
      var on = d.preferences.indexOf(o.v) >= 0
      return '<button class="cc8-kink' + (on ? ' on' : '') + '" type="button" data-act="toggle-pref" data-value="' + esc(o.v) + '" aria-pressed="' + on + '">' + esc(o.l) + '</button>'
    }).join('')
    return '<section class="cc8-step" data-cc8-screen-body data-screen="7">' +
      '<h2 class="cc8-h2">Preferences</h2>' +
      '<p class="cc8-sub">Pick up to 5 — or let us surprise you</p>' +
      '<div class="cc8-kinks">' + chips + '</div>' +
      '<button class="cc8-surprise" type="button" data-act="surprise-prefs">Surprise me</button>' +
      '</section>'
  }

  /* ── summary ──────────────────────────────────────────────────────── */

  function displayVal(group, value, fallback) {
    if (group === 'ethnicity') return value ? capitalize(pronoun()) + ' is ' + (ETH_LABEL[value] || value) : (fallback || 'Surprise me')
    if (value) return labelOf(group, value)
    return fallback || 'Surprise me'
  }
  function hairText() {
    var d = draft()
    if (!d.hairstyle && !d.color) return 'Surprise me'
    var c = d.color ? labelOf('color', d.color) : ''
    var s = d.hairstyle ? labelOf('hairstyle', d.hairstyle) : ''
    if (c && s) return c + ' with ' + s
    return c || s
  }
  function prefsText() {
    var d = draft()
    if (!d.preferences.length) return 'Surprise me'
    var labels = d.preferences.map(function (v) { return labelOf('preferences', v) })
    return capitalize(pronoun()) + ' is into ' + labels.join(', ') + '.'
  }
  function bioText() {
    var d = draft()
    var age = d.ageRange ? ageLabel(d.ageRange) : ''
    return (d.name || 'Unnamed') + (age ? ', ' + age : '')
  }

  function cardHtml(field, title, value, cls, img, thumb) {
    var visual = ''
    if (img) visual = '<img src="' + esc(img) + '" alt="" loading="lazy" draggable="false"' + (thumb ? ' class="cc8-thumb"' : '') + '>'
    else if (cls === 'cc8-card-appearance') visual = placeholderHtml(title, 'body')
    else if (cls === 'cc8-card-eth') visual = placeholderHtml(title, 'eth')
    return '<div class="cc8-card ' + cls + '" data-act="edit" data-field="' + field + '" role="button" tabindex="0" aria-label="Edit ' + esc(title) + '">' +
      visual +
      '<div class="' + (img ? (thumb ? 'cc8-eth-txt' : 'cc8-app-txt') : '') + '"><h4>' + esc(title) + '</h4><div class="cc8-val">' + esc(value) + '</div></div>' +
      '<button class="cc8-edit" type="button" data-act="edit" data-field="' + field + '" aria-label="Edit ' + esc(title) + '">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>' +
      '</button></div>'
  }

  var COLOR_HEX = {
    brunette: '#6b4a33', blonde: '#e3c07a', black: '#1b1b1b', red: '#b5522c',
    pink: '#e58ab5', white: '#e8e1d5', blue: '#5b86c9', green: '#6f8f6a',
    purple: '#8b6bb1', multicolor: 'linear-gradient(135deg,#f08 0%,#08f 100%)'
  }
  function hairCardHtml() {
    var d = draft()
    var img = d.hairstyle ? assetFor('hairstyle', d.hairstyle) : ''
    var sw = d.color && COLOR_HEX[d.color]
      ? '<span class="cc8-hair-swatch" style="background:' + COLOR_HEX[d.color] + '" title="' + esc(labelOf('color', d.color)) + '"></span>'
      : ''
    var inner = img
      ? '<img src="' + esc(img) + '" alt="" loading="lazy" draggable="false">'
      : placeholderHtml('Hairstyle', 'hair')
    return '<div class="cc8-card cc8-card-hair" data-act="edit" data-field="hairstyle" role="button" tabindex="0" aria-label="Edit hairstyle">' +
      inner + sw +
      '<div class="cc8-hair-txt"><h4>Hairstyle</h4><div class="cc8-val">' + esc(hairText()) + '</div></div>' +
      '<button class="cc8-edit" type="button" data-act="edit" data-field="hairstyle" aria-label="Edit hairstyle">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>' +
      '</button></div>'
  }

  function appearanceCards(onlySet) {
    var d = draft()
    var defs = (state.gender === 'male'
      ? [{ f: 'body', t: 'Body type' }, { f: 'chest', t: 'Chest' }, { f: 'butt', t: 'Butt size' }]
      : [{ f: 'body', t: 'Body type' }, { f: 'breast', t: 'Breast size' }, { f: 'butt', t: 'Butt size' }]).filter(function (x) {
        return groupAvailable(x.f) && (!onlySet || d[x.f])
      })
    return defs.map(function (x) {
      var img = d[x.f] ? assetFor(x.f, d[x.f]) : ''
      return cardHtml(x.f, x.t, displayVal(x.f, d[x.f]), 'cc8-card-appearance', img)
    }).join('')
  }

  function screenSummary() {
    var d = draft()
    var prompt = !!d.promptMode
    var apps = appearanceCards(prompt)
    var showHair = !prompt || d.hairstyle
    var showEth = !prompt || d.ethnicity
    var html = '<section class="cc8-step" data-cc8-screen-body data-screen="8">' +
      '<h2 class="cc8-h2">Your selection</h2>' +
      '<p class="cc8-sub">' + (prompt ? 'Your written description is saved with these choices' : 'Tap any card to change just that detail') + '</p>' +
      missingNote() +
      '<div class="cc8-summary' + (prompt ? ' cc8-summary-prompt' : '') + '">' +
        cardHtml('bio', 'Bio', bioText(), 'cc8-card-bio') +
        (showHair ? hairCardHtml() : '') +
        (showEth ? cardHtml('ethnicity', 'Ethnicity', displayVal('ethnicity', d.ethnicity), 'cc8-card-eth', d.ethnicity ? assetFor('ethnicity', d.ethnicity) : '', true) : '') +
        (apps ? '<div class="cc8-appearance-row' + (state.gender === 'male' ? ' two' : '') + '">' + apps + '</div>' : '') +
        cardHtml('relationship', 'Relationship to you', displayVal('relationship', d.relationship), 'cc8-card-rel') +
        cardHtml('occupation', 'Occupation', displayVal('occupation', d.occupation), 'cc8-card-occ') +
        cardHtml('personality', 'Personality', displayVal('personality', d.personality), 'cc8-card-pers') +
        (d.promptMode && d.appearance_description ? cardHtml('appearance_description', 'Custom appearance', d.appearance_description, 'cc8-card-prefs') : '') +
        (d.promptMode && d.personality_description ? cardHtml('personality_description', 'Custom personality', d.personality_description, 'cc8-card-prefs') : '') +
        cardHtml('preferences', 'Preferences', prefsText(), 'cc8-card-prefs') +
      '</div>' +
      '</section>'
    return html
  }

  function missingNote() {
    var d = draft()
    var relevant = Object.keys(state.missing).filter(function (k) {
      return k.indexOf(d.gender + '|' + (d.style || '')) === 0 && k.indexOf('|entry|') < 0
    })
    if (!relevant.length) return ''
    return '<div class="cc8-missing-note">Some artwork for this combination is not ready yet (' + relevant.length + ' ' +
      (relevant.length === 1 ? 'slot' : 'slots') + '). ' +
      'Those cards show a neutral placeholder and are still selectable — no other ethnicity or gender is used as a stand-in.</div>'
  }

  /* ── picker / edit modal ──────────────────────────────────────────── */

  function openModal(field) {
    var d = draft()
    var multi = field === 'preferences'
    var temp
    if (field === 'bio') temp = { name: d.name, ageRange: d.ageRange }
    else if (multi) temp = d.preferences.slice()
    else temp = d[field] || ''
    state.modal = { field: field, temp: temp, query: '' }
    state.lastFocus = document.activeElement
    renderModal()
    var first = $('.cc8-sheet', els.layer)
    if (first) first.focus()
  }
  function closeModal(restore) {
    var field = state.modal && state.modal.field
    state.modal = null
    els.layer.innerHTML = ''
    document.body.classList.remove('cc8-lock')
    if (restore && field) {
      var target = $('[data-act="edit"][data-field="' + field + '"]', els.inner)
      if (target) target.focus()
    }
  }
  function modalTitle(field) {
    if (field === 'bio') return 'Bio'
    if (field === 'ethnicity') return 'Ethnicity'
    if (field === 'body') return 'Body type'
    if (field === 'breast' || field === 'chest') return state.gender === 'male' ? 'Chest' : 'Breast size'
    if (field === 'butt') return 'Butt size'
    if (field === 'hairstyle') return 'Hairstyle'
    if (field === 'color') return 'Hair color'
    if (field === 'relationship') return 'Relationship to you'
    if (field === 'occupation') return 'Occupation'
    if (field === 'personality') return 'Personality'
    if (field === 'preferences') return 'Preferences'
    return 'Edit'
  }
  function renderModal() {
    var m = state.modal
    if (!m) return
    document.body.classList.add('cc8-lock')
    els.layer.innerHTML = '<div class="cc8-layer" id="cc8Modal">' +
      '<div class="cc8-mask" data-act="modal-cancel"></div>' +
      '<div class="cc8-sheet" role="dialog" aria-modal="true" aria-labelledby="cc8ModalTitle" tabindex="-1">' +
        '<div class="cc8-sheet-head"><button class="cc8-cancel" type="button" data-act="modal-cancel">Cancel</button>' +
          '<h3 id="cc8ModalTitle">' + esc(modalTitle(m.field)) + '</h3>' +
          '<button class="cc8-save" type="button" data-act="modal-save">Save</button></div>' +
        '<div class="cc8-sheet-body" id="cc8ModalBody">' + modalBody(m) + '</div>' +
      '</div></div>'
  }
  function modalBody(m) {
    var f = m.field
    if (f === 'bio') {
      var ages = AGE_RANGES.map(function (a) {
        return '<button class="cc8-pill' + (m.temp.ageRange === a.v ? ' on' : '') + '" type="button" data-act="modal-age" data-value="' + a.v + '">' + a.l + '</button>'
      }).join('')
      return '<label class="cc8-sheet-hint" for="cc8ModalName">Name</label>' +
        '<input class="cc8-search" id="cc8ModalName" data-input="modal-name" maxlength="32" value="' + esc(m.temp.name) + '" autocomplete="off">' +
        '<div class="cc8-sheet-hint" style="margin-top:16px">Age range</div><div class="cc8-pills" style="justify-content:flex-start">' + ages + '</div>'
    }
    if (f === 'preferences') {
      var kinks = optList('preferences').map(function (o) {
        var on = m.temp.indexOf(o.v) >= 0
        return '<button class="cc8-kink' + (on ? ' on' : '') + '" type="button" data-act="modal-toggle" data-value="' + esc(o.v) + '" aria-pressed="' + on + '">' + esc(o.l) + '</button>'
      }).join('')
      return '<p class="cc8-sheet-hint">Pick up to 5. Saved choices only change this row.</p><div class="cc8-kinks" style="padding:0">' + kinks + '</div>'
    }
    if (f === 'ethnicity') {
      return '<div class="cc8-grid3">' + ETHNICITIES.map(function (o) { return modalOptionCard('ethnicity', o) }).join('') + '</div>'
    }
    if (f === 'body' || f === 'breast' || f === 'chest' || f === 'butt' || f === 'hairstyle') {
      return '<div class="cc8-grid3">' + optList(f).map(function (o) { return modalOptionCard(f, o) }).join('') + '</div>'
    }
    if (f === 'color') {
      return '<div class="cc8-swatches" style="flex-wrap:wrap;padding:4px 0">' + optList('color').map(function (o) { return modalSwatch(o) }).join('') + '</div>'
    }
    var list = optList(f)
    var q = (m.query || '').toLowerCase()
    var filtered = list.filter(function (o) { return !q || o.l.toLowerCase().indexOf(q) >= 0 })
    var rows = '<button class="cc8-lrow' + (!m.temp ? ' on' : '') + '" type="button" data-act="modal-pick" data-group="' + f + '" data-value="">Surprise me<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg></button>'
    rows += filtered.map(function (o) {
      var on = m.temp === o.v
      return '<button class="cc8-lrow' + (on ? ' on' : '') + '" type="button" data-act="modal-pick" data-group="' + f + '" data-value="' + esc(o.v) + '">' + esc(o.l) +
        (on ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>' : '') + '</button>'
    }).join('')
    if (!filtered.length) rows += '<div class="cc8-empty">No matches</div>'
    return '<input class="cc8-search" data-input="modal-search" placeholder="Search…" value="' + esc(m.query) + '" autocomplete="off"><div class="cc8-list">' + rows + '</div>'
  }
  function modalOptionCard(group, o) {
    var m = state.modal
    var on = m && m.temp === o.v
    var img = assetFor(group, o.v)
    var inner = img ? '<img src="' + esc(img) + '" alt="" loading="lazy" draggable="false">' : placeholderHtml(o.l, group === 'hairstyle' ? 'hair' : 'eth')
    return '<button class="cc8-opt' + (on ? ' on' : '') + '" type="button" data-act="modal-pick" data-group="' + group + '" data-value="' + esc(o.v) + '">' + inner + '<span class="cc8-opt-label">' + esc(o.l) + '</span></button>'
  }
  function modalSwatch(o) {
    var m = state.modal
    var on = m && m.temp === o.v
    var bg = COLORS[o.v] || '#333'
    var style = bg.indexOf('gradient') >= 0 ? 'background-image:' + bg : 'background:' + bg
    return '<button class="cc8-swatch' + (on ? ' on' : '') + '" type="button" data-act="modal-pick" data-group="color" data-value="' + esc(o.v) + '"><i style="' + style + '"></i>' + esc(o.l) + '</button>'
  }
  function saveModal() {
    var m = state.modal
    if (!m) return
    var d = draft()
    if (m.field === 'bio') {
      var name = String(m.temp.name || '').trim()
      if (!name) { toast('Give ' + objectPronoun() + ' a name first'); return }
      d.name = name
      d.ageRange = m.temp.ageRange || ''
    } else if (m.field === 'preferences') {
      d.preferences = m.temp.slice(0, 5)
    } else if (m.field === 'appearance_description' || m.field === 'personality_description') {
      closeModal(true)
      openPrompt()
      return
    } else {
      d[m.field] = m.temp || ''
    }
    saveDrafts()
    var field = m.field
    closeModal(false)
    render()
    focusEdit(field)
  }
  function focusEdit(field) {
    if (!field) return
    var target = $('[data-act="edit"][data-field="' + field + '"]', els.inner) ||
      $('[data-act="open-picker"][data-field="' + field + '"]', els.inner)
    if (target) target.focus()
  }

  /* ── free prompt ──────────────────────────────────────────────────── */

  function openPrompt() {
    state.prompt = true
    state.lastFocus = document.activeElement
    var d = draft()
    var title = state.gender === 'male' ? 'Describe your AI boyfriend' : 'Describe your AI girlfriend'
    els.layer.innerHTML = '<div class="cc8-prompt" id="cc8Prompt" data-cc8-prompt>' +
      '<div class="cc8-prompt-head"><button class="cc8-iconbtn" type="button" data-act="prompt-close" aria-label="Back">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>' +
        '<h2>' + esc(title) + '</h2><span></span></div>' +
      '<div class="cc8-prompt-body"><div class="cc8-prompt-inner">' +
        '<label for="cc8PromptName">Name</label>' +
        '<div class="cc8-field" style="margin-bottom:18px"><input id="cc8PromptName" data-input="name" maxlength="32" autocomplete="off" placeholder="' + (state.gender === 'male' ? 'His name' : 'Her name') + '" value="' + esc(d.name) + '"></div>' +
        '<label for="cc8Appearance">Appearance <small>up to 1000 characters</small></label>' +
        '<textarea id="cc8Appearance" data-input="appearance_description" maxlength="1000" placeholder="Face, body, hair, style, the way ' + pronoun() + ' looks…">' + esc(d.appearance_description) + '</textarea>' +
        '<div class="cc8-count" data-count-for="appearance_description">' + d.appearance_description.length + '/1000</div>' +
        '<label for="cc8Personality">Personality <small>up to 1000 characters</small></label>' +
        '<textarea id="cc8Personality" data-input="personality_description" maxlength="1000" placeholder="How ' + pronoun() + ' thinks, flirts, talks and treats you…">' + esc(d.personality_description) + '</textarea>' +
        '<div class="cc8-count" data-count-for="personality_description">' + d.personality_description.length + '/1000</div>' +
      '</div></div>' +
      '<div class="cc8-prompt-actions">' +
        '<button class="cc8-ghostbtn" type="button" data-act="prompt-surprise">Surprise me</button>' +
        '<button class="cc8-ghostbtn" type="button" data-act="prompt-presets">Use presets</button>' +
        '<button class="cc8-cta" type="button" data-act="prompt-continue">Continue</button>' +
      '</div></div>'
    document.body.classList.add('cc8-lock')
    var ta = document.getElementById('cc8Appearance')
    if (ta && window.matchMedia('(min-width: 1024px)').matches) ta.focus()
  }
  function closePrompt() {
    state.prompt = false
    els.layer.innerHTML = ''
    document.body.classList.remove('cc8-lock')
    var back = state.lastFocus
    if (back && document.contains(back)) back.focus()
  }
  function promptSurprise() {
    var d = draft()
    if (!String(d.name || '').trim()) {
      var pool = NAME_POOL[state.gender]
      d.name = pool[Math.floor(Math.random() * pool.length)]
      var nameInput = document.getElementById('cc8PromptName')
      if (nameInput) nameInput.value = d.name
    }
    var eth = ETH_LABEL[d.ethnicity] || ''
    var male = state.gender === 'male'
    if (!String(d.appearance_description || '').trim()) d.appearance_description = male
      ? 'A handsome ' + (eth ? eth.toLowerCase() + ' ' : '') + 'man in his late twenties, short dark hair, warm brown eyes, a relaxed smile and an easy, athletic build. He dresses in a simple black shirt and jeans.'
      : 'A beautiful ' + (eth ? eth.toLowerCase() + ' ' : '') + 'woman in her mid-twenties, long wavy dark hair, warm brown eyes, soft freckles and a relaxed smile. She wears an oversized shirt and delicate jewellery.'
    if (!String(d.personality_description || '').trim()) d.personality_description = male
      ? 'Confident and protective, with a dry sense of humour. He notices the small things, teases you gently, and always follows through on what he says.'
      : 'Warm, playful and a little teasing. She remembers the small things, laughs easily, and makes you feel like the only person in the room.'
    saveDrafts()
    var a = document.getElementById('cc8Appearance')
    var p = document.getElementById('cc8Personality')
    if (a) a.value = d.appearance_description
    if (p) p.value = d.personality_description
    updateCounters()
  }
  function updateCounters() {
    $all('[data-count-for]', els.layer).forEach(function (el) {
      var key = el.getAttribute('data-count-for')
      var ta = $('[data-input="' + key + '"]', els.layer)
      el.textContent = (ta ? ta.value.length : 0) + '/1000'
    })
  }
  function continuePrompt() {
    var d = draft()
    d.name = String(d.name || '').trim()
    if (!d.name) { toast('Add a name to continue'); return }
    if (!String(d.appearance_description || '').trim() && !String(d.personality_description || '').trim()) {
      toast('Describe ' + objectPronoun() + ' or use Surprise me'); return
    }
    d.promptMode = true
    if (!d.style) d.style = 'Realistic'
    saveDrafts()
    closePrompt()
    go(8)
  }

  /* ── generation ───────────────────────────────────────────────────── */

  function isLoggedIn() {
    try { return !!localStorage.getItem(TOKEN_KEY) } catch (e) { return false }
  }
  function redirectUrl() {
    var params = []
    if (/[?&]legacy=1(?:&|$)/.test(location.search)) params.push('legacy=1')
    return location.pathname + (params.length ? '?' + params.join('&') : '')
  }
  function openLogin(mode) {
    var req = { mode: mode, redirect: redirectUrl() }
    if (window.FyLogin && typeof window.FyLogin.open === 'function') {
      window.FyLogin.open(req)
      return
    }
    location.href = './Login.html?redirect=' + encodeURIComponent(req.redirect)
  }
  function handleAuthFail(code) {
    if (code !== 'A0100' && code !== 'A0101' && code !== '10003' && code !== '10004') return false
    try { localStorage.removeItem(TOKEN_KEY) } catch (e) {}
    stopPolling()
    closeGen()
    toast('Please log in again to continue')
    setTimeout(function () { openLogin('signin') }, 60)
    return true
  }

  function buildPayload() {
    var d = draft()
    var p = {
      name: d.name,
      gender: state.gender,
      style: d.style,
      type: d.style,
      race: ETH_BACKEND[d.ethnicity] || d.ethnicity || '',
      ethnicity: ETH_LABEL[d.ethnicity] || d.ethnicity || '',
      body: labelOf('body', d.body),
      hairstyle: labelOf('hairstyle', d.hairstyle),
      hair: labelOf('color', d.color),
      age_range: d.ageRange,
      age: ageInt(d.ageRange),
      relationship: d.relationship,
      occupation: d.occupation,
      personality: d.personality,
      preferences: d.preferences,
      kinks: d.preferences
    }
    if (state.gender === 'male') {
      p.chest = labelOf('chest', d.chest)
      if (d.clothing) p.clothing = d.clothing
      if (d.voice) p.voice = d.voice
    } else {
      p.butt_size = BUTT_LABEL[d.butt] || labelOf('butt', d.butt)
      p.breast = labelOf('breast', d.breast)
      p.clothing = d.clothing || 'Bikini'
      p.voice = d.voice || 'sweet'
    }
    if (d.promptMode) {
      p.appearance_description = d.appearance_description || ''
      p.personality_description = d.personality_description || ''
    }
    return p
  }

  function signature() { return JSON.stringify(buildPayload()) }

  function startGeneration() {
    if (state.submitting) return
    var d = draft()
    if (state.screen !== 8) { go(8); return }
    if (!String(d.name || '').trim()) { go(5); toast('Add a name first'); return }
    if (!d.style) { go(1); toast('Choose a style first'); return }
    saveDrafts()
    if (!isLoggedIn()) { showGuestPreview(); return }
    if (state.created && state.created.signature === signature()) {
      if (state.created.phase === 'failed') { showGenFailed(state.created.message || 'Portrait generation failed. Try again.'); return }
      if (state.created.phase === 'done' && state.created.imageUrl) { showRealGeneration(state.created.imageUrl, 'done'); return }
      showRealGeneration('', 'anim')
      startPolling()
      return
    }
    submitCreate()
  }

  function postJSON(path, body, timeout) {
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null
    var timer = setTimeout(function () { if (ctrl) ctrl.abort() }, timeout || 25000)
    var headers = { 'Content-Type': 'application/json' }
    var token = ''
    try { token = localStorage.getItem(TOKEN_KEY) || '' } catch (e) {}
    if (token) headers.Authorization = 'Bearer ' + token
    return fetch(API + path, {
      method: 'POST', headers: headers, body: JSON.stringify(body), signal: ctrl ? ctrl.signal : undefined
    }).then(function (r) {
      clearTimeout(timer)
      return r.json().catch(function () { return { code: 'HTTP_' + r.status, msg: 'HTTP ' + r.status } })
        .then(function (d) { d.__status = r.status; return d })
    }, function (err) { clearTimeout(timer); throw err })
  }

  function submitCreate() {
    state.submitting = true
    var cta = document.getElementById('cc8Cta')
    if (cta) { cta.disabled = true; cta.textContent = 'Creating…' }
    var payload = buildPayload()
    var sig = signature()
    postJSON('/customRoleCreate', payload, 25000).then(function (d) {
      state.submitting = false
      if (handleAuthFail(d && d.code)) return
      if (!d || d.code !== '00000' || !d.data || !d.data.id) {
        var msg = (d && (d.msg || d.message)) || 'Creation failed'
        showGenFailed(msg)
        return
      }
      var result = {
        personaId: d.data.id,
        imageTaskId: d.data.image_task_id || 0,
        signature: sig,
        profile: JSON.parse(JSON.stringify(draft())),
        phase: 'anim',
        imageUrl: '',
        prices: normalizePrices(d.data.prices),
        balance: parseFloat(d.data.balance) || 0,
        createdAt: Date.now()
      }
      state.created = result
      saveResult(result)
      showRealGeneration('', 'anim')
      startPolling()
    }).catch(function () {
      state.submitting = false
      showGenFailed('Network error — your draft is safe. Try again.')
    })
  }

  function genTasks() {
    var him = objectPronoun()
    return ['Reading your choices', 'Mixing ' + him + ' a look', 'Painting the portrait', 'Adding the finishing touches', 'Almost ready']
  }
  function genMarkup(phase, imageUrl, message) {
    var tasks = genTasks()
    var checked = phase === 'done' ? tasks.length : phase === 'failed' ? 2 : 0
    var list = tasks.map(function (t, i) {
      return '<li class="' + (i < checked ? 'on' : '') + '" data-task="' + i + '"><span class="cc8-tick"></span><span>' + esc(t) + '</span></li>'
    }).join('')
    var img = imageUrl ? '<img src="' + esc(imageUrl) + '" alt="" draggable="false">' : (state.previewData ? '<img src="' + esc(state.previewData) + '" alt="" draggable="false">' : '')
    var card = '<div class="cc8-gen-card' + (phase === 'done' ? ' done' : '') + '" id="cc8GenCard">' + img +
      '<div class="cc8-gen-shade"></div>' +
      '<div class="cc8-gen-panel" id="cc8GenPanel">' +
        '<h3>' + (state.gender === 'male' ? 'Bringing him to life' : 'Bringing her to life') + '</h3>' +
        '<ul class="cc8-tasks" id="cc8Tasks">' + list + '</ul>' +
        '<div class="cc8-gen-bar"><i id="cc8GenBar" style="width:' + (phase === 'done' ? 100 : 8) + '%"></i></div>' +
        '<p class="cc8-gen-status" id="cc8GenStatus">' + esc(message || (phase === 'done' ? 'Ready.' : 'This usually takes a moment.')) + '</p>' +
      '</div></div>'
    return card
  }
  function showGuestPreview() {
    state.gen = { kind: 'guest', phase: 'anim', imageUrl: previewImage() }
    state.previewData = previewImage()
    renderGen()
    var step = 0
    clearInterval(state.genTimer)
    state.genTimer = setInterval(function () {
      step++
      var lis = $all('#cc8Tasks li')
      lis.forEach(function (li, i) { if (i < step) li.classList.add('on') })
      var bar = document.getElementById('cc8GenBar')
      if (bar) bar.style.width = Math.min(100, Math.round(step / 5 * 100)) + '%'
      if (step >= 5) {
        clearInterval(state.genTimer)
        state.genTimer = null
        setTimeout(function () {
          if (!state.gen || state.gen.kind !== 'guest') return
          state.gen.phase = 'gate'
          renderGen()
        }, 420)
      }
    }, 460)
  }
  function showRealGeneration(imageUrl, phase, message) {
    state.gen = { kind: 'real', phase: phase || 'anim', imageUrl: imageUrl || '' }
    state.previewData = previewImage()
    renderGen()
  }
  function showGenFailed(message) {
    state.gen = { kind: 'real', phase: 'failed', imageUrl: '', message: message }
    state.previewData = previewImage()
    renderGen()
  }
  function renderGen() {
    var g = state.gen
    if (!g) { els.layer.innerHTML = ''; document.body.classList.remove('cc8-lock'); return }
    document.body.classList.add('cc8-lock')
    var phase = g.phase
    var html = '<div class="cc8-gen" id="cc8Gen" data-gen-phase="' + phase + '">' +
      '<div class="cc8-gen-top"><h2>' + (phase === 'done' ? (state.gender === 'male' ? 'He is ready' : 'She is ready') : phase === 'failed' ? 'Almost there' : 'Creating your ' + (state.gender === 'male' ? 'AI boyfriend' : 'AI girlfriend')) + '</h2>' +
        '<button class="cc8-iconbtn" type="button" data-act="gen-close" aria-label="Close"' + (phase === 'anim' ? ' disabled' : '') + '>' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>' +
      genMarkup(phase, g.imageUrl, g.message) +
      '<div class="cc8-gen-actions" id="cc8GenActions">' + genActions(phase) + '</div>' +
    '</div>'
    els.layer.innerHTML = html
    var card = document.getElementById('cc8GenCard')
    if (card && phase === 'anim' && state.gen.kind === 'real' && !$('#cc8Tasks .on')) startTaskTicker()
    if (phase === 'gate') renderGate()
  }
  function genActions(phase) {
    if (phase === 'anim') return '<p class="cc8-gen-status">You can leave this page — we will keep the draft.</p>'
    if (phase === 'failed') {
      if (state.created && state.created.personaId) {
        var f = prices()
        return '<button class="cc8-cta" type="button" data-act="gen-save">Save to My AI · 💰' + esc(f.save) + '</button>' +
          '<button class="cc8-ghostbtn" type="button" data-act="gen-regen">Regenerate · 💰' + esc(f.regen) + '</button>' +
          '<div class="cc8-balance">Balance: <b id="cc8Bal">' + esc(balance()) + '</b> tokens</div>' +
          '<button class="cc8-ghostbtn" type="button" data-act="gen-close">Back to edit</button>'
      }
      return '<button class="cc8-cta" type="button" data-act="gen-retry">Try again</button>' +
        '<button class="cc8-ghostbtn" type="button" data-act="gen-close">Back to edit</button>'
    }
    if (phase === 'done') {
      var pr = prices()
      return '<button class="cc8-cta" type="button" data-act="gen-save">Save to My AI · 💰' + esc(pr.save) + '</button>' +
        '<button class="cc8-ghostbtn" type="button" data-act="gen-regen">Regenerate · 💰' + esc(pr.regen) + '</button>' +
        '<div class="cc8-balance">Balance: <b id="cc8Bal">' + esc(balance()) + '</b> tokens</div>'
    }
    return ''
  }
  function startTaskTicker() {
    var step = 1
    clearInterval(state.genTimer)
    state.genTimer = setInterval(function () {
      var lis = $all('#cc8Tasks li')
      if (!lis.length) { clearInterval(state.genTimer); state.genTimer = null; return }
      if (step < lis.length) lis[step].classList.add('on')
      step++
      var bar = document.getElementById('cc8GenBar')
      if (bar) bar.style.width = Math.min(92, 8 + step * 16) + '%'
      if (step > lis.length + 2) { clearInterval(state.genTimer); state.genTimer = null }
    }, 900)
  }
  function renderGate() {
    var panel = document.getElementById('cc8GenPanel')
    if (!panel) return
    panel.innerHTML = '<h3>' + (state.gender === 'male' ? 'Your AI boyfriend is ready to meet you' : 'Your AI girlfriend is ready to meet you') + '</h3>' +
      '<p class="cc8-gen-status" style="text-align:left;margin:0 0 10px">This was a preview — no character was created and nothing was charged.</p>' +
      '<button class="cc8-cta" type="button" data-act="signup" id="cc8Signup" style="width:100%;margin-bottom:8px">Sign up for free</button>' +
      '<button class="cc8-ghostbtn" type="button" data-act="signin" id="cc8Signin" style="width:100%">Log in</button>' +
      '<p class="cc8-fine" style="margin:10px 0 0;color:#888;font-size:11px">Your draft is saved on this device.</p>'
    var actions = document.getElementById('cc8GenActions')
    if (actions) actions.innerHTML = ''
  }
  function closeGen() {
    if (state.gen && state.gen.phase === 'anim' && state.gen.kind === 'guest') return
    clearInterval(state.genTimer)
    state.genTimer = null
    state.gen = null
    state.previewData = ''
    els.layer.innerHTML = ''
    document.body.classList.remove('cc8-lock')
    if (state.screen !== 8) go(8)
  }
  function previewImage() {
    var d = draft()
    if (!d.style) return ''
    if (d.ethnicity) {
      var e = assetFor('ethnicity', d.ethnicity)
      if (e) return e
    }
    if (d.hairstyle) {
      var h = assetFor('hairstyle', d.hairstyle)
      if (h) return h
    }
    return entryAsset(d.style)
  }

  /* ── paid actions / media validation ─────────────────────────────── */

  var DEFAULT_PRICES = { regen: 1, save: 10 }
  function normalizePrices(p) {
    p = p || {}
    return {
      regen: isFinite(parseFloat(p.regen)) ? parseFloat(p.regen) : DEFAULT_PRICES.regen,
      save: isFinite(parseFloat(p.save)) ? parseFloat(p.save) : DEFAULT_PRICES.save
    }
  }
  function prices() { return normalizePrices(state.created && state.created.prices) }
  function balance() {
    var b = state.created ? parseFloat(state.created.balance) : 0
    return isFinite(b) ? Math.round(b * 100) / 100 : 0
  }
  function setBalance(v) {
    if (!state.created) return
    var n = parseFloat(v)
    if (!isFinite(n)) return
    state.created.balance = Math.round(n * 100) / 100
    saveResult(state.created)
    var el = document.getElementById('cc8Bal')
    if (el) el.textContent = String(state.created.balance)
  }
  function safeMediaUrl(u) {
    if (!u || typeof u !== 'string') return ''
    u = u.trim()
    /* reject legacy dirty values such as '/https://cdn/...' */
    if (/^\/https?:/i.test(u)) return ''
    if (/^\/(public\/)?storage\/[\w./-]+\.(jpe?g|png|webp|avif|gif)$/i.test(u)) return u
    if (/^\/(sec|pub)\/[\w./-]+\.(jpe?g|png|webp|avif|gif)(\?[\w=&%.-]+)?$/i.test(u)) return u
    try {
      var x = new URL(u)
      if (x.protocol !== 'https:') return ''
      var hostOk = /^media\./i.test(x.hostname)
      if (!hostOk && Array.isArray(window.CC8_MEDIA_HOSTS)) {
        hostOk = window.CC8_MEDIA_HOSTS.some(function (h) { return String(h).toLowerCase() === x.hostname.toLowerCase() })
      }
      if (!hostOk) return ''
      if (!/^\/(sec|pub)\//.test(x.pathname)) return ''
      return u
    } catch (e) { return '' }
  }

  function openConfirm(kind) {
    if (!state.created || state.submitting || state.saving) return
    var price = kind === 'save' ? prices().save : prices().regen
    var bal = balance()
    var enough = bal >= price
    var old = document.getElementById('cc8Confirm')
    if (old) old.remove()
    var box = document.createElement('div')
    box.className = 'cc8-confirm'
    box.id = 'cc8Confirm'
    box.innerHTML = '<div class="cc8-confirm-card" role="dialog" aria-modal="true" aria-labelledby="cc8ConfirmTitle">' +
      '<h3 id="cc8ConfirmTitle">' + (kind === 'save' ? 'Save to My AI' : 'Regenerate portrait') + '</h3>' +
      '<p>' + (kind === 'save' ? 'This unlocks chat with your AI.' : 'A new portrait will be generated from the same choices.') + '</p>' +
      '<div class="cc8-confirm-row"><span>Price</span><b>💰' + esc(price) + ' tokens</b></div>' +
      '<div class="cc8-confirm-row"><span>Balance</span><b>' + esc(bal) + ' tokens</b></div>' +
      (enough ? '' : '<p class="cc8-confirm-warn">Not enough tokens. Top up to continue.</p>') +
      '<div class="cc8-confirm-actions">' +
        (enough ? '' : '<a class="cc8-cta" href="./purchasePoints.html">Top up</a>') +
        '<button class="cc8-ghostbtn" type="button" data-act="confirm-cancel">Cancel</button>' +
        '<button class="cc8-cta" type="button" data-act="confirm-do" data-kind="' + kind + '"' + (enough ? '' : ' disabled') + '>Confirm · 💰' + esc(price) + '</button>' +
      '</div></div>'
    var host = document.getElementById('cc8Gen') || els.layer
    host.appendChild(box)
  }
  function closeConfirm() {
    var el = document.getElementById('cc8Confirm')
    if (el) el.remove()
  }

  /* ── polling / save / regenerate ──────────────────────────────────── */

  function stopPolling() {
    if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null }
    state.pollBusy = false
  }
  function statusOnce(result, cb) {
    var token = ''
    try { token = localStorage.getItem(TOKEN_KEY) || '' } catch (e) {}
    fetch(API + '/customRoleImageStatus?persona_id=' + encodeURIComponent(result.personaId), {
      headers: token ? { Authorization: 'Bearer ' + token } : {}, cache: 'no-store'
    }).then(function (r) { return r.json() }).then(cb).catch(function () { cb(null) })
  }
  function applyStatus(d, result) {
    if (handleAuthFail(d && d.code)) return 'auth'
    if (!d || d.code !== '00000' || !d.data) return 'reject'
    if (d.data.persona_id != null && String(d.data.persona_id) !== String(result.personaId)) return 'reject'
    var st = d.data.status
    if (st === 'completed') {
      var url = safeMediaUrl(d.data.image_url)
      if (!url) return 'badmedia'
      stopPolling()
      result.phase = 'done'
      result.imageUrl = url
      saveResult(result)
      showRealGeneration(url, 'done')
      return 'done'
    }
    if (st === 'failed') {
      stopPolling()
      showGenFailed(d.data.msg || 'Portrait generation failed. Try again.')
      return 'failed'
    }
    return st === 'none' ? 'none' : 'pending'
  }
  function startPolling() {
    stopPolling()
    var result = state.created
    if (!result || !result.personaId) return
    var started = Date.now()
    var errors = 0, noneTicks = 0, ticks = 0
    var LIMIT = 20 * 60 * 1000
    function tick() {
      if (state.pollBusy || !state.created) return
      state.pollBusy = true
      ticks++
      statusOnce(result, function (d) {
        state.pollBusy = false
        if (!state.created || state.created.personaId !== result.personaId) return
        if (state.gen && state.gen.phase === 'failed') return
        if (Date.now() - started > LIMIT) {
          stopPolling()
          result.phase = 'failed'
          result.message = 'The portrait is taking longer than usual. It will appear in My AI when ready.'
          saveResult(result)
          showGenFailed(result.message)
          return
        }
        var r = applyStatus(d, result)
        if (r === 'auth' || r === 'done' || r === 'failed') return
        if (r === 'reject') {
          errors++
          if (errors >= 6) {
            stopPolling()
            result.phase = 'failed'
            result.message = 'We could not confirm the portrait status. Try again in a moment.'
            saveResult(result)
            showGenFailed(result.message)
          }
          return
        }
        errors = 0
        if (r === 'badmedia') {
          stopPolling()
          result.phase = 'failed'
          result.message = 'The portrait URL did not pass the media check. Try regenerating.'
          saveResult(result)
          showGenFailed(result.message)
          return
        }
        if (r === 'none') {
          noneTicks++
          /* legacy account with no generation service: never poll forever */
          if (!result.imageTaskId && noneTicks >= 6) {
            stopPolling()
            result.phase = 'failed'
            result.message = 'Portrait is delayed — your AI is already available in My AI.'
            saveResult(result)
            showGenFailed(result.message)
          }
        }
      })
    }
    setTimeout(tick, 1200)
    state.pollTimer = setInterval(tick, 5000)
  }
  function restoreGeneration(saved) {
    state.created = saved
    state.screen = 8
    showRealGeneration('', 'anim')
    var done = false
    statusOnce(saved, function (d) {
      if (done) return
      done = true
      if (!d || d.code !== '00000' || !d.data) {
        state.created = null
        saveResult(null)
        closeGen()
        toast('Could not verify that portrait for this account')
        return
      }
      var r = applyStatus(d, saved)
      if (r === 'pending' || r === 'none') startPolling()
    })
  }

  function regenerate() {
    if (!state.created || !state.created.personaId) return
    if (state.submitting) return
    if (balance() < prices().regen) { toast('Not enough tokens'); return }
    state.submitting = true
    var btn = $('[data-act="gen-regen"]')
    if (btn) { btn.disabled = true; btn.textContent = 'Regenerating…' }
    postJSON('/customRoleRegenerate', { persona_id: state.created.personaId }).then(function (d) {
      state.submitting = false
      if (handleAuthFail(d && d.code)) return
      if (!d || d.code !== '00000') {
        if (btn) { btn.disabled = false; btn.textContent = 'Regenerate · 💰' + prices().regen }
        toast((d && d.msg) || 'Regenerate failed'); return
      }
      if (d.data && d.data.balance != null) setBalance(d.data.balance)
      else setBalance(balance() - prices().regen)
      state.created.imageTaskId = (d.data && d.data.image_task_id) || 0
      state.created.phase = 'anim'
      state.created.imageUrl = ''
      saveResult(state.created)
      showRealGeneration('', 'anim')
      startPolling()
    }).catch(function () { state.submitting = false; toast('Network error') })
  }
  function saveCharacter() {
    if (!state.created || !state.created.personaId) return
    if (state.saving) return
    if (balance() < prices().save) { toast('Not enough tokens'); return }
    state.saving = true
    var btn = $('[data-act="gen-save"]')
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…' }
    postJSON('/customRoleSave', { persona_id: state.created.personaId }).then(function (d) {
      state.saving = false
      if (handleAuthFail(d && d.code)) return
      if (d && d.code === '00000') {
        if (d.data && d.data.balance != null) setBalance(d.data.balance)
        else setBalance(balance() - prices().save)
        state.created.saved = true
        saveResult(state.created)
        var actions = document.getElementById('cc8GenActions')
        if (actions) {
          actions.innerHTML = '<a class="cc8-cta" id="cc8GoChat" href="./Chat.html?id=my-' + encodeURIComponent(state.created.personaId) + '" style="display:flex;align-items:center;justify-content:center;text-decoration:none">Chat with ' + objectPronoun() + ' now</a>' +
            '<button class="cc8-ghostbtn" type="button" data-act="gen-close">Back to selection</button>'
        }
        toast('Saved to My AI')
      } else {
        if (btn) { btn.disabled = false; btn.textContent = 'Save to My AI · 💰' + prices().save }
        toast((d && d.msg) || 'Save failed')
      }
    }).catch(function () {
      state.saving = false
      if (btn) { btn.disabled = false; btn.textContent = 'Save to My AI · 💰' + prices().save }
      toast('Network error')
    })
  }

  /* ── navigation / events ──────────────────────────────────────────── */

  function go(n) {
    n = Math.max(1, Math.min(8, parseInt(n, 10) || 1))
    if (state.prompt) closePrompt()
    state.screen = n
    draft().step = n
    saveDrafts()
    render()
  }
  function next() {
    if (ctaBlocked()) {
      if (state.screen === 1) toast('Pick Realistic or Anime to start')
      else if (state.screen === 2) toast('Choose an ethnicity to continue')
      else if (state.screen === 5) toast('Add a name to continue')
      return
    }
    if (state.screen >= 8) { startGeneration(); return }
    go(state.screen + 1)
  }
  function back() {
    if (state.gen) return
    if (state.prompt) { closePrompt(); return }
    if (state.screen > 1) go(state.screen - 1)
  }
  function randomName() {
    var pool = NAME_POOL[state.gender]
    var d = draft()
    d.name = pool[Math.floor(Math.random() * pool.length)]
    saveDrafts()
    var input = document.getElementById('cc8Name')
    if (input) input.value = d.name
    updateCtaState()
  }
  function togglePref(v) {
    var d = draft()
    var i = d.preferences.indexOf(v)
    if (i >= 0) d.preferences.splice(i, 1)
    else if (d.preferences.length < 5) d.preferences.push(v)
    else { toast('Pick up to 5'); return }
    saveDrafts()
    render()
  }
  function surprisePrefs() {
    var d = draft()
    var pool = optList('preferences').map(function (o) { return o.v })
    var picked = []
    while (picked.length < 3 && pool.length) {
      var i = Math.floor(Math.random() * pool.length)
      picked.push(pool.splice(i, 1)[0])
    }
    d.preferences = picked
    saveDrafts()
    render()
  }

  function onClick(e) {
    var t = e.target && e.target.closest ? e.target.closest('[data-act]') : null
    if (!t) return
    var act = t.getAttribute('data-act')
    if (act === 'close') {
      if (state.gen && state.gen.phase === 'anim') return
      location.href = './Home.html'
      return
    }
    if (act === 'back') { back(); return }
    if (act === 'gender') {
      var g = t.getAttribute('data-gender')
      if (g !== 'female' && g !== 'male') return
      state.gender = g
      state.screen = 1
      draft().step = 1
      saveDrafts()
      render()
      return
    }
    if (act === 'style') {
      draft().style = t.getAttribute('data-style')
      saveDrafts()
      render()
      return
    }
    if (act === 'pick') {
      var group = t.getAttribute('data-group')
      var value = t.getAttribute('data-value')
      if (group === 'ageRange') draft().ageRange = value
      else draft()[group] = value
      saveDrafts()
      render()
      return
    }
    if (act === 'random-name') { randomName(); return }
    if (act === 'open-picker' || act === 'edit') {
      var field = t.getAttribute('data-field')
      if (field === 'appearance_description' || field === 'personality_description') { openPrompt(); return }
      openModal(field)
      return
    }
    if (act === 'toggle-pref') { togglePref(t.getAttribute('data-value')); return }
    if (act === 'surprise-prefs') { surprisePrefs(); return }
    if (act === 'cta') { next(); return }
    if (act === 'prompt') { openPrompt(); return }
    if (act === 'prompt-close') { closePrompt(); return }
    if (act === 'prompt-surprise') { promptSurprise(); return }
    if (act === 'prompt-presets') {
      draft().promptMode = false
      saveDrafts()
      closePrompt()
      go(1)
      return
    }
    if (act === 'prompt-continue') { continuePrompt(); return }
    if (act === 'modal-cancel') { closeModal(true); return }
    if (act === 'modal-save') { saveModal(); return }
    if (act === 'modal-pick') {
      if (!state.modal) return
      state.modal.temp = t.getAttribute('data-value') || ''
      renderModal()
      var body = document.getElementById('cc8ModalBody')
      if (body) body.scrollTop = 0
      return
    }
    if (act === 'modal-age') {
      if (!state.modal) return
      state.modal.temp.ageRange = t.getAttribute('data-value')
      renderModal()
      return
    }
    if (act === 'modal-toggle') {
      if (!state.modal) return
      var v = t.getAttribute('data-value')
      var arr = state.modal.temp
      var idx = arr.indexOf(v)
      if (idx >= 0) arr.splice(idx, 1)
      else if (arr.length < 5) arr.push(v)
      else { toast('Pick up to 5'); return }
      renderModal()
      return
    }
    if (act === 'signup') { openLogin('signup'); return }
    if (act === 'signin') { openLogin('signin'); return }
    if (act === 'gen-close') { closeGen(); return }
    if (act === 'gen-retry') {
      clearInterval(state.genTimer)
      state.gen = null
      els.layer.innerHTML = ''
      document.body.classList.remove('cc8-lock')
      if (state.created && state.created.personaId) {
        state.created.phase = 'anim'
        saveResult(state.created)
        showRealGeneration('', 'anim')
        startPolling()
      } else {
        submitCreate()
      }
      return
    }
    if (act === 'gen-regen') { openConfirm('regen'); return }
    if (act === 'gen-save') { openConfirm('save'); return }
    if (act === 'confirm-cancel') { closeConfirm(); return }
    if (act === 'confirm-do') {
      var kind = t.getAttribute('data-kind')
      closeConfirm()
      if (kind === 'save') saveCharacter(); else regenerate()
      return
    }
    if (act === 'gen-chat') {
      if (!state.created) return
      location.href = './Chat.html?id=my-' + encodeURIComponent(state.created.personaId)
      return
    }
  }

  function onInput(e) {
    var t = e.target
    if (!t || !t.getAttribute) return
    var key = t.getAttribute('data-input')
    if (!key) return
    if (key === 'name') {
      draft().name = t.value
      saveDrafts()
      updateCtaState()
      return
    }
    if (key === 'appearance_description' || key === 'personality_description') {
      draft()[key] = t.value
      saveDrafts()
      updateCounters()
      return
    }
    if (key === 'modal-name') {
      if (state.modal) state.modal.temp.name = t.value
      return
    }
    if (key === 'modal-search') {
      if (!state.modal) return
      state.modal.query = t.value
      var body = document.getElementById('cc8ModalBody')
      if (!body) return
      var pos = t.selectionStart
      body.innerHTML = modalBody(state.modal)
      var input = $('[data-input="modal-search"]', body)
      if (input) { input.focus(); try { input.setSelectionRange(pos, pos) } catch (err) {} }
    }
  }

  function onKey(e) {
    if (e.key === 'Escape') {
      if (state.modal) { closeModal(true); e.preventDefault(); return }
      if (state.prompt) { closePrompt(); e.preventDefault(); return }
      if (state.gen && state.gen.phase !== 'anim') { closeGen(); e.preventDefault(); return }
    }
    if (e.key === 'Enter' && !state.modal && !state.prompt && !state.gen) {
      var tag = (e.target && e.target.tagName || '').toLowerCase()
      if (tag === 'textarea') return
      if (state.screen < 8) { e.preventDefault(); next() }
    }
    if ((e.key === 'Enter' || e.key === ' ') && e.target && e.target.getAttribute && e.target.getAttribute('role') === 'button') {
      e.preventDefault()
      e.target.click()
    }
  }

  root.addEventListener('click', onClick)
  root.addEventListener('input', onInput)
  document.addEventListener('keydown', onKey)
  window.addEventListener('beforeunload', function () { stopPolling() })

  function toast(msg) {
    var old = document.querySelector('.cc8-toast')
    if (old) old.remove()
    var el = document.createElement('div')
    el.className = 'cc8-toast'
    el.setAttribute('role', 'status')
    el.textContent = msg
    document.body.appendChild(el)
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el) }, 2600)
  }

  /* ── deep links / test hooks ──────────────────────────────────────── */

  function applyQuery() {
    var q = new URLSearchParams(location.search)
    var g = cleanText(q.get('gender') || q.get('g'), 8).toLowerCase()
    if (g === 'male' || g === 'female') state.gender = g
    var d = draft()
    var style = cleanText(q.get('style') || q.get('s'), 12).toLowerCase()
    if (style === 'realistic') d.style = 'Realistic'
    else if (style === 'anime') d.style = 'Anime'
    var eth = cleanText(q.get('ethnicity') || q.get('e'), 24).toLowerCase()
    if (Object.prototype.hasOwnProperty.call(ETH_LABEL, eth)) d.ethnicity = eth
    /* Reference deep links: p=body, b=breast, bu=butt, a=age number, step=7=summary. */
    var body = cleanText(q.get('p') || q.get('body'), 24).toLowerCase()
    if (hasOption('body', body)) d.body = body
    var bust = cleanText(q.get('b') || q.get('breast'), 24).toLowerCase()
    if (hasOption(state.gender === 'male' ? 'chest' : 'breast', bust)) {
      if (state.gender === 'male') d.chest = bust; else d.breast = bust
    }
    var butt = cleanText(q.get('bu') || q.get('butt'), 24).toLowerCase()
    if (hasOption('butt', butt)) d.butt = butt
    var ha = cleanText(q.get('ha') || q.get('hairstyle'), 24).toLowerCase()
    var haOpt = optionFromLabel('hairstyle', ha)
    if (haOpt) d.hairstyle = haOpt
    var hac = cleanText(q.get('hac') || q.get('color'), 24).toLowerCase()
    if (hasOption('color', hac)) d.color = hac
    var n = q.get('name') || q.get('n')
    if (n) d.name = cleanText(n, DRAFT_LIMITS.name)
    var rel = q.get('rel') || q.get('relationship')
    if (rel) d.relationship = optionFromLabel('relationship', rel)
    var occ = q.get('occ') || q.get('occupation')
    if (occ) d.occupation = optionFromLabel('occupation', occ)
    var per = q.get('per') || q.get('pers') || q.get('personality')
    if (per) d.personality = optionFromLabel('personality', per)
    var age = cleanText(q.get('age') || q.get('a'), 12)
    if (age) {
      if (validAgeRange(age)) d.ageRange = age
      else {
        var num = parseInt(age, 10)
        if (num > 0) d.ageRange = num >= 45 ? '45+' : num >= 35 ? '35-44' : num >= 25 ? '25-34' : '18-24'
      }
    }
    var step = parseInt(q.get('step'), 10)
    if (step) {
      /* The reference wizard's 7 steps are not v8's 7th screen. 6=preferences,
         7=summary; keep 1–5 aligned, clamp anything else into 8. */
      var map = { 1: 1, 2: 2, 3: 3, 4: 4, 5: 5, 6: 7, 7: 8 }
      state.screen = map[step] || 8
    } else state.screen = d.step || 1
    d.step = state.screen
  }

  function hasQuery() {
    return /(?:^|[?&])(step|gender|g|style|s|ethnicity|e|p|body|b|breast|bu|butt|ha|hairstyle|hac|color|name|n|age|a)=/.test(location.search)
  }

  window.CC8 = {
    version: VERSION,
    go: go,
    next: next,
    back: back,
    getState: function () {
      return { gender: state.gender, screen: state.screen, draft: JSON.parse(JSON.stringify(draft())), missing: missingList() }
    },
    missing: missingList,
    payload: buildPayload,
    openPrompt: openPrompt,
    reset: function () {
      state.drafts = { female: blankDraft('female'), male: blankDraft('male') }
      state.gender = 'female'
      state.screen = 1
      state.created = null
      saveDrafts()
      saveResult(null)
      render()
    }
  }
  function missingList() {
    return Object.keys(state.missing).sort().map(function (k) {
      return { key: k, count: state.missing[k] }
    })
  }

  /* ── boot ─────────────────────────────────────────────────────────── */

  function init() {
    loadState()
    applyQuery()
    if (hasQuery()) saveDrafts()
    var saved = loadResult()
    if (saved && saved.personaId && saved.signature === signature() && isLoggedIn()) {
      restoreGeneration(saved)
    }
    render()
    loadManifest().then(function () {
      if (state.manifest) render()
    })
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      document.documentElement.setAttribute('data-cc8-reduced-motion', '1')
    }
    try {
      document.dispatchEvent(new CustomEvent('cc8:ready', { detail: { version: VERSION, screen: state.screen } }))
    } catch (e) {}
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init)
  else init()
})()
