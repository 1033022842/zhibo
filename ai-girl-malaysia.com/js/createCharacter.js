/* createCharacter — candy.ai characters_v2 同款单页向导
 * 入口(Realistic/Anime) → 6 步属性 → 最终表单（弹窗选择）→ Bring my AI To Life
 * 提交 POST /api/live/customRoleCreate（值直接送英文文案，兼容聊天侧 persona_desc 拼句）
 * 成功后轮询 GET /api/live/customRoleImageStatus?persona_id=N 等形象图生成（worker v6.2 + t2i workflow）
 * 选项图直接复用旧向导存档目录里的 webp/svg（线上已部署，零新增资源）；
 * 发色沿用旧版 CSS 色块（含动漫线扩展色）；缺图的选项（Shy/Queen 等）走渐变兜底，运营可后补。
 */
(function () {
    var API = '/api/live'
    var STORE_KEY = 'cc_profile_v2'
    var STEP_KEY = 'cc_step_v2'

    /* ── 选项图片（candy.ai characters/new4 同款素材，自托管于 assets/create/）── */
    var IMG = {
        entry: {
            Realistic: './assets/create/entry-realistic.webp',
            Anime: './assets/create/entry-anime.webp',
        },
        race: {
            Realistic: {
                'Caucasian': './assets/create/eth-r-caucasian.webp',
                'Asian': './assets/create/eth-r-asian.webp',
                'Black / Afro': './assets/create/eth-r-blackafro.webp',
                'Latina': './assets/create/eth-r-latina.webp',
                'Arab': './assets/create/eth-r-arab.webp',
            },
            Anime: {
                'Caucasian': './assets/create/eth-a-caucasian.webp',
                'Asian': './assets/create/eth-a-asian.webp',
                'Latina': './assets/create/eth-a-latina.webp',
            },
        },
        hairstyle: {
            Realistic: {
                'Straight': './assets/create/hair-r-straight.webp',
                'Bangs': './assets/create/hair-r-bangs.webp',
                'Curly': './assets/create/hair-r-curly.webp',
                'Bun': './assets/create/hair-r-bun.webp',
                'Short': './assets/create/hair-r-short.webp',
                'Ponytail': './assets/create/hair-r-ponytail.webp',
            },
            Anime: {
                'Straight': './assets/create/hair-a-straight.webp',
                'Bangs': './assets/create/hair-a-bangs.webp',
                'Curly': './assets/create/hair-a-curly.webp',
                'Bun': './assets/create/hair-a-bun.webp',
                'Short': './assets/create/hair-a-short.webp',
                'Ponytail': './assets/create/hair-a-ponytail.webp',
            },
        },
        hairColor: {
            Realistic: {
                'Brunette': './assets/create/color-r-brunette.webp',
                'Blonde': './assets/create/color-r-blonde.webp',
                'Black': './assets/create/color-r-black.webp',
                'Red': './assets/create/color-r-red.webp',
                'Pink': './assets/create/color-r-pink.webp',
            },
            Anime: {
                'Brunette': './assets/create/color-a-brunette.webp',
                'Blonde': './assets/create/color-a-blonde.webp',
                'Black': './assets/create/color-a-black.webp',
                'Red': './assets/create/color-a-red.webp',
                'Pink': './assets/create/color-a-pink.webp',
                'White': './assets/create/color-a-white.webp',
                'Blue': './assets/create/color-a-blue.webp',
                'Green': './assets/create/color-a-green.webp',
                'Purple': './assets/create/color-a-purple.webp',
                'Multicolor': './assets/create/color-a-multicolor.webp',
            },
        },
        eye: {
            Realistic: {
                'Brown': './assets/create/eye-r-brown.webp',
                'Blue': './assets/create/eye-r-blue.webp',
                'Green': './assets/create/eye-r-green.webp',
            },
            Anime: {
                'Brown': './assets/create/eye-a-brown.webp',
                'Blue': './assets/create/eye-a-blue.webp',
                'Green': './assets/create/eye-a-green.webp',
                'Red': './assets/create/eye-a-red.webp',
                'Yellow': './assets/create/eye-a-yellow.webp',
            },
        },
        body: {
            Realistic: {
                'Skinny': './assets/create/body-r-skinny.webp',
                'Athletic': './assets/create/body-r-athletic.webp',
                'Average': './assets/create/body-r-average.webp',
                'Curvy': './assets/create/body-r-curvy.webp',
                'BBW': './assets/create/body-r-bbw.webp',
            },
            Anime: {
                'Skinny': './assets/create/body-a-skinny.webp',
                'Athletic': './assets/create/body-a-athletic.webp',
                'Average': './assets/create/body-a-average.webp',
                'Curvy': './assets/create/body-a-curvy.webp',
            },
        },
        breast: {
            Realistic: {
                'Small': './assets/create/breast-r-small.webp',
                'Medium': './assets/create/breast-r-medium.webp',
                'Large': './assets/create/breast-r-large.webp',
                'Extra Large': './assets/create/breast-r-xl.webp',
            },
            Anime: {
                'Small': './assets/create/breast-a-small.webp',
                'Medium': './assets/create/breast-a-medium.webp',
                'Large': './assets/create/breast-a-large.webp',
                'Extra Large': './assets/create/breast-a-xl.webp',
            },
        },
        personality: {
            'Nympho': './assets/create/personality-nympho.webp',
            'Lover': './assets/create/personality-lover.webp',
            'Submissive': './assets/create/personality-submissive.webp',
            'Dominant': './assets/create/personality-dominant.webp',
            'Temptress': './assets/create/personality-temptress.webp',
            'Innocent': './assets/create/personality-innocent.webp',
            'Caregiver': './assets/create/personality-caregiver.webp',
            'Experimenter': './assets/create/personality-experimenter.webp',
            'Mean': './assets/create/personality-mean.webp',
            'Confidant': './assets/create/personality-confidant.webp',
            'Shy': './assets/create/personality-shy.webp',
            'Queen': './assets/create/personality-queen.webp',
        },
        relation: {
            'Stranger': './chooseRelationship_files/stranger-511e976a210ce74743b082e8aaa27e296b54db0bc0276a522cb21c890fdfd4e7.svg',
            'Girlfriend': './chooseRelationship_files/girlfriend-1738933ee5a8e4353c59c395e568791490c8979c026bfbee814b0248076af8b8.svg',
            'Sex Friend': './chooseRelationship_files/sex_friend-8415470f12b5fa0e07df036bc19dc7a6c35b7ec9263312b24915a366c8f18496.svg',
            'School Mate': './chooseRelationship_files/school_mate-e9fb307b9864de646dedd83bd438c64fa521acc44ac03facd9d116203aed57ad.svg',
            'Work Colleague': './chooseRelationship_files/colleague-db5bafdd2d26b17e4c01652ce260d600231143aac100e6bb6058e6956c1e85b7.svg',
            'Wife': './chooseRelationship_files/wife-a4b306151171181d8be0d4c4ed8e9ea7c6d8e306e212eee1ec867c2060309db9.svg',
            'Mistress': './chooseRelationship_files/mistress-6afbebafc6aba6ee72008e0ee5b2f174f2ccec6893ae22215d53cb3a917d44db.svg',
            'Friend': './chooseRelationship_files/friend-26357362378aa627bcb9803f217ecc97c1a98f0e710ae605751f13b004215796.svg',
            'Step Sister': './chooseRelationship_files/step_sister-56c5bfa1fd7221c1a714cc8ea0ffb82ab2bace00f6db2753655ef1771655935c.svg',
            'Step Mom': './chooseRelationship_files/step_mom-f9e41e118a6dafa181c9a717875a91b7cd3ca4ce3bb337c7d57cf70f596c8ab8.svg',
            /* candy 的 Relationship 是纯文字卡（无图）——上面旧 SVG 仅供我们的弹窗用；Step Daughter 等无素材项走渐变兜底 */
        },
    }

    /* ── 选项数据（文案照搬 candy.ai characters/new4）──────────────── */
    var CATEGORY_STEPS = [
        { key: 'race', title: 'Choose Ethnicity', field: 'race', options: ['Caucasian', 'Asian', 'Black / Afro', 'Latina', 'Arab'],
            animeHide: ['Black / Afro', 'Arab'] },
        { key: 'hairstyle', title: 'Choose Hair Style', field: 'hairstyle', options: ['Straight', 'Bangs', 'Curly', 'Bun', 'Short', 'Ponytail'] },
        { key: 'hair', title: 'Choose Hair Color', field: 'hair',
            options: ['Brunette', 'Blonde', 'Black', 'Red', 'Pink'],
            animeExtra: ['White', 'Blue', 'Green', 'Purple', 'Multicolor'] },
        { key: 'eye', title: 'Choose Eye Color', field: 'eye',
            options: ['Brown', 'Blue', 'Green'],
            animeExtra: ['Red', 'Yellow'] },
        { key: 'body', title: 'Choose Body Type', field: 'body',
            options: ['Skinny', 'Athletic', 'Average', 'Curvy', 'BBW'],
            animeHide: ['BBW'] },
        { key: 'breast', title: 'Choose Breast Size', field: 'breast', options: ['Small', 'Medium', 'Large', 'Extra Large'] },
    ]
    /* candy new4 的分屏：Ethnicity 独占一屏；Hair Style+Hair Color+Eye Color 同屏；Body Type+Breast Size 同屏。
       （candy 的 Back 箭头只出现在 Ethnicity / Hair Style / Body Type / 名字页之前） */
    var SCREENS = [
        { groups: ['race'] },
        { groups: ['hairstyle', 'hair', 'eye'] },
        { groups: ['body', 'breast'] },
    ]
    var STEP_IMG_FIELD = { race: 'race', hairstyle: 'hairstyle', eye: 'eye', body: 'body', breast: 'breast', hair: 'hairColor' }
    var MODAL_IMG = { personality: 'personality', relation: 'relation' }

    var PERSONALITIES = ['Nympho', 'Lover', 'Submissive', 'Dominant', 'Temptress', 'Innocent', 'Caregiver', 'Experimenter', 'Mean', 'Confidant', 'Shy', 'Queen']
    var RELATIONSHIPS = ['Stranger', 'Girlfriend', 'Sex Friend', 'School Mate', 'Work Colleague', 'Wife', 'Mistress', 'Friend', 'Step Sister', 'Step Mom', 'Step Daughter', 'Landlord', 'Sugar Baby', 'Boss', 'Teacher', 'Student', 'Neighbour', 'Mother-In-Law', 'Sister-In-Law']
    var OCCUPATIONS = ['🎓 Student', '💃 Dancer', '👗 Model', '👠 Stripper', '🧹 Maid', '💻 Cam Girl', '👔 Boss / CEO', '🎥 Pornstar', '🎮 Streamer', '🍹 Bartender', '💻 Tech Engineer', '🛟 Lifeguard', '💵 Cashier', '💆‍♀️ Massage Therapist', '👩‍🏫 Teacher', '💉 Nurse', '💼 Secretary', '🧘‍♀️ Yoga Instructor', '🏋️‍♂️ Fitness Coach', '👨‍🍳 Cook', '🖌️ Artist', '🎬 Movie Star/Actress', '🩺 Doctor', '📚 Librarian', '🕵️‍♂️ Spy', '👮 Police Officer', '🪖 Soldier', '⚖️ Lawyer', '💇‍♀️ Hairdresser', '🦷 Dentist', '🎤 Singer/Musician', '👩‍⚕️ Gynecologist', '✍️ Writer', '✈️ Flight Attendant', '🏅 Professional Athlete', '🔬 Scientist', '🌸 Florist', '💄 Makeup Artist', '📸 Photographer', '🤝 Social Worker', '🎨 Designer', '💊 Pharmacist', '🥦 Nutritionist']
    var KINKS = ['Bondage', 'Spanking', 'Collar & Leash', 'Punishment', 'Humiliation', 'Public Play', 'Roleplay', 'Anal Play', 'Oral Play', 'Cum Play', 'Creampie', 'Squirting', 'Dirty Talk', 'Breeding', 'Daddy Dominance', 'Edging', 'Obedience', 'Control', 'Inexperienced', 'Shy Flirting', 'Playful Teasing', 'Cuddling', 'Slow & Sensual', 'Hair Pulling']
    var VOICES = [
        { id: 'Voice 1 (Confident)', name: 'Voice 1', tag: 'Confident', pitch: 0.9, rate: 1.0 },
        { id: 'Voice 2 (Cheerful)', name: 'Voice 2', tag: 'Cheerful', pitch: 1.3, rate: 1.1 },
        { id: 'Voice 3 (Dominant)', name: 'Voice 3', tag: 'Dominant', pitch: 0.8, rate: 0.9 },
        { id: 'Voice 4 (Innocent)', name: 'Voice 4', tag: 'Innocent', pitch: 1.4, rate: 1.0 },
        { id: 'Voice 5 (Sweet)', name: 'Voice 5', tag: 'Sweet', pitch: 1.25, rate: 0.95 },
        { id: 'Voice 6 (Sultry)', name: 'Voice 6', tag: 'Sultry', pitch: 0.85, rate: 0.85 },
        { id: 'Voice 7 (Calm)', name: 'Voice 7', tag: 'Calm', pitch: 1.0, rate: 0.9 },
        { id: 'Voice 8 (Thoughtful)', name: 'Voice 8', tag: 'Thoughtful', pitch: 1.05, rate: 0.85 },
        { id: 'Voice 9 (Whimsical)', name: 'Voice 9', tag: 'Whimsical', pitch: 1.2, rate: 1.05 },
    ]
    var NAME_POOL = ['Luna', 'Aria', 'Mia', 'Chloe', 'Zoe', 'Ruby', 'Ivy', 'Stella', 'Nova', 'Bella', 'Zara', 'Zoey', 'Lily', 'Rose', 'Daisy', 'Zelda', 'Willow', 'Autumn', 'Sienna', 'Skylar', 'Aurora', 'Cleo', 'Jade', 'Nora', 'Riley', 'Sasha', 'Tessa', 'Violet', 'Wren', 'Yara', 'Aiko', 'Mei', 'Sakura', 'Yuki', 'Camila', 'Lucia', 'Sofia', 'Valentina', 'Amara', 'Zahra', 'Layla', 'Nadia', 'Aisha', 'Priya', 'Elena', 'Katya', 'Mila', 'Anya', 'Freya']
    var SAMPLE_LINE = "Hi there... I'm so happy you made me just for you."
    var FEMALE_VOICE_RE = /(female|woman|girl|zira|samantha|karen|moira|tessa|victoria|serena|allison|ava|susan|joanna|kendra|kimberly|salli|ivy|amy|emma|olivia|sophia|isabella|chloe|grace|hazel|libby|sonia|natasha|aria|jenny|michelle|ana)/i
    var FALLBACK_ICON = { personality: '💫', relation: '💞' }

    /* ── 状态 ─────────────────────────────────────── */
    var profile = {
        category: '',      // 'Realistic' | 'Anime'
        race: '', hairstyle: '', hair: '', eye: '', body: '', breast: '',
        name: '', age: 22,
        personality: '', relation: '', profession: '',
        kinks: [],         // 最多 3 个
        voice: '',
    }
    var stepIndex = 0          // 0=入口，1..3=SCREENS（candy 分屏），4=最终表单
    var submitted = false      // 提交后进入生成/完成视图
    var pollTimer = null
    var personaId = 0
    var imageTaskId = 0
    var currentModal = ''      // 'personality'|'relation'|'profession'|'kinks'|'voice'
    var modalDraft = []        // 弹窗内未保存的选择

    function $(id) { return document.getElementById(id) }

    function loadState() {
        try {
            var saved = JSON.parse(localStorage.getItem(STORE_KEY) || 'null')
            if (saved) {
                for (var k in profile) {
                    if (saved[k] !== undefined) profile[k] = saved[k]
                }
            }
            stepIndex = parseInt(localStorage.getItem(STEP_KEY) || '0', 10) || 0
            if (stepIndex < 0 || stepIndex > 4) stepIndex = 0
        } catch (e) { /* 忽略坏数据 */ }
    }
    function saveState() {
        localStorage.setItem(STORE_KEY, JSON.stringify(profile))
        localStorage.setItem(STEP_KEY, String(stepIndex))
    }
    function clearState() {
        localStorage.removeItem(STORE_KEY)
        localStorage.removeItem(STEP_KEY)
    }

    function stepsForCategory() {
        return CATEGORY_STEPS.map(function (s) {
            var opts = s.options.slice()
            if (profile.category === 'Anime' && s.animeExtra) {
                opts = opts.concat(s.animeExtra)
            }
            if (profile.category === 'Anime' && s.animeHide) {
                opts = opts.filter(function (o) { return s.animeHide.indexOf(o) < 0 })
            }
            return { key: s.key, title: s.title, field: s.field, swatch: !!s.swatch, options: opts }
        })
    }

    /* 已选值在分类切换后可能不在新选项集里（如 Anime 无 BBW）→ 校验清理 */
    function sanitizeForCategory() {
        stepsForCategory().forEach(function (s) {
            if (profile[s.field] && s.options.indexOf(profile[s.field]) < 0) {
                profile[s.field] = ''
            }
        })
    }

    /* ── 渲染工具 ─────────────────────────────────── */
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    function dotsHtml(active) {
        // 5 段进度：入口 + Ethnicity + Hair&Eyes + Body + 最终表单（candy 分屏口径）
        var html = ''
        for (var i = 0; i < 5; i++) {
            html += '<i class="' + (i <= active ? 'on' : '') + '"></i>'
        }
        return html
    }

    /* 选项卡的视觉面：图 / 色块 / 兜底 emoji */
    function optVisual(fieldKey, opt, fallbackIcon) {
        var map = IMG[fieldKey]
        if (fieldKey === 'hairSwatch') {
            var color = map[opt] || '#3a3a3a'
            var isGrad = color.indexOf('gradient') >= 0
            return '<span class="cc-swatch' + (isGrad ? ' grad' : '') + '" style="' + (isGrad ? 'background-image:' : 'background:') + color + '"></span>'
        }
        var img = map && map[profile.category] && map[profile.category][opt]
        if (img) {
            return '<img class="cc-opt-img" src="' + img + '" alt="' + esc(opt) + '" loading="lazy">'
        }
        return '<span class="cc-opt-fallback">' + (fallbackIcon || '✨') + '</span>'
    }

    /* ── 渲染：入口步（Start 走悬浮胶囊，candy 同款）────────────── */
    function renderEntry() {
        var html = ''
        html += '<div class="cc-step">'
        html += '<div class="cc-dots">' + dotsHtml(0) + '</div>'
        html += '<div class="cc-entry-title">Create your Dream <span class="pink">AI Girl</span></div>'
        html += '<div class="cc-entry-sub">She looks, sounds and acts exactly how you want.</div>'
        html += '<div class="cc-cards">'
        ;['Realistic', 'Anime'].forEach(function (cat) {
            html += '<div class="cc-card' + (profile.category === cat ? ' on' : '') + '" data-cat="' + cat + '" role="button">'
            html += '<div class="cc-card-imgwrap"><img src="' + IMG.entry[cat] + '" alt="' + cat + '"><div class="cc-card-check">✓</div></div>'
            html += '<div class="cc-card-label">' + cat + '</div>'
            html += '</div>'
        })
        html += '</div></div>'
        $('ccMain').innerHTML = html

        $('ccMain').querySelectorAll('.cc-card').forEach(function (card) {
            card.addEventListener('click', function () {
                var cat = card.getAttribute('data-cat')
                if (profile.category !== cat) {
                    profile.category = cat
                    sanitizeForCategory()
                }
                saveState()
                renderEntry()
                updateFab()
            })
        })
        updateFab()
    }

    /* ── 渲染：属性屏（candy 分屏：多段同屏，无内联 Next，前进走右下悬浮钮）── */
    function groupDef(key) {
        return CATEGORY_STEPS.filter(function (s) { return s.key === key })[0]
    }

    function screenComplete(screenIdx) {
        return SCREENS[screenIdx - 1].groups.every(function (key) {
            return !!profile[groupDef(key).field]
        })
    }

    function renderScreen() {
        var screenIdx = stepIndex // 1..3
        var groups = SCREENS[screenIdx - 1].groups.map(groupDef)
        var first = groups[0]
        var html = '<div class="cc-step">'
        html += '<div class="cc-dots">' + dotsHtml(screenIdx) + '</div>'
        html += '<div class="cc-step-head"><button class="cc-back" id="ccBack" type="button">←</button>'
        html += '<div class="cc-step-title">' + esc(first.title) + '</div></div>'
        html += '<div class="cc-step-sub">' + (profile.category === 'Anime' ? 'Anime · ' : 'Realistic · ') + 'Step ' + screenIdx + ' of 3</div>'
        groups.forEach(function (s, gi) {
            var fieldKey = STEP_IMG_FIELD[s.key]
            if (gi > 0) html += '<div class="cc-section-title">' + esc(s.title) + '</div>'
            var opts = s.options.slice()
            if (profile.category === 'Anime' && s.animeExtra) opts = opts.concat(s.animeExtra)
            if (profile.category === 'Anime' && s.animeHide) {
                opts = opts.filter(function (o) { return s.animeHide.indexOf(o) < 0 })
            }
            // candy 金字塔排版：每段一行铺满（≤6 单行；>6 对半两行）
            var cols = opts.length <= 6 ? opts.length : Math.ceil(opts.length / 2)
            html += '<div class="cc-options" style="--cols:' + cols + '">'
            opts.forEach(function (opt) {
                html += '<div class="cc-opt' + (profile[s.field] === opt ? ' on' : '') + '" data-opt="' + esc(opt) + '" data-field="' + s.field + '" role="button">'
                html += '<div class="cc-opt-check">✓</div>'
                html += optVisual(fieldKey, opt)
                html += '<div class="cc-opt-label">' + esc(opt) + '</div>'
                html += '</div>'
            })
            html += '</div>'
        })
        html += '</div>'
        $('ccMain').innerHTML = html

        $('ccBack').addEventListener('click', function () {
            stepIndex -= 1
            saveState()
            render()
        })
        $('ccMain').querySelectorAll('.cc-opt').forEach(function (el) {
            el.addEventListener('click', function () {
                var opt = el.getAttribute('data-opt')
                // 找到所属 group（同一屏多个 grid，按 data-field 区分）
                var field = el.getAttribute('data-field')
                profile[field] = opt
                saveState()
                renderScreen()
                refreshFab()
            })
        })
        updateFab()
    }

    /* ── 悬浮前进钮（candy 同款：底部居中胶囊，Start/NEXT 共用）────── */
    function updateFab() {
        var fab = $('ccFab')
        if (!fab) return
        if (submitted) { fab.style.display = 'none'; return }
        if (stepIndex === 0) {
            fab.style.display = 'flex'
            fab.disabled = !profile.category
            fab.textContent = 'Start →'
        } else if (stepIndex >= 1 && stepIndex <= 3) {
            fab.style.display = 'flex'
            fab.disabled = !screenComplete(stepIndex)
            fab.textContent = 'NEXT →'
        } else {
            fab.style.display = 'none'
        }
    }
    function refreshFab() { updateFab() }

    function fabNext() {
        if (stepIndex === 0) {
            if (!profile.category) return
            stepIndex = 1
        } else if (stepIndex >= 1 && stepIndex <= 3) {
            if (!screenComplete(stepIndex)) return
            stepIndex += 1
        } else {
            return
        }
        saveState()
        render()
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }

    /* ── 渲染：最终表单 ───────────────────────────── */
    function renderFinal() {
        var html = '<div class="cc-step">'
        html += '<div class="cc-dots">' + dotsHtml(7) + '</div>'
        html += '<div class="cc-step-head"><button class="cc-back" id="ccBack" type="button">←</button>'
        html += '<div class="cc-step-title">Final touches</div></div>'
        html += '<div class="cc-step-sub">Give her a name and a voice — then bring her to life.</div>'
        html += '<div class="cc-form">'

        // Name
        html += '<div class="cc-field"><div class="cc-field-label"><span>Name *</span><span class="cc-count" id="ccNameCount">' + (profile.name || '').length + '/20</span></div>'
        html += '<div class="cc-name-row"><input class="cc-input" id="ccName" maxlength="20" placeholder="e.g. Luna" value="' + esc(profile.name) + '">'
        html += '<button class="cc-refresh" id="ccNameRefresh" type="button" title="Random name">⟳</button></div></div>'

        // Age 18-55
        html += '<div class="cc-field"><div class="cc-field-label"><span>Age</span></div>'
        html += '<div class="cc-age-strip" id="ccAgeStrip">'
        for (var a = 18; a <= 55; a++) {
            html += '<button class="cc-age-chip' + (profile.age === a ? ' on' : '') + '" data-age="' + a + '" type="button">' + a + '</button>'
        }
        html += '</div></div>'

        // 弹窗触发行
        html += pickerRow('personality', 'Choose Personality', profile.personality)
        html += pickerRow('relation', 'Choose Relationship', profile.relation)
        html += pickerRow('profession', 'Choose Occupation', profile.profession)
        html += pickerRow('kinks', 'What kinks are they into? (Max. 3)', (profile.kinks || []).join(', '))
        html += pickerRow('voice', 'Choose Voice', profile.voice)

        // candy 同款大 CTA：渐变 + 扫光 + 呼吸发光
        html += '<button class="cc-cta-big" id="ccCta" type="button"><span>Bring my AI To Life</span></button>'
        html += '<div class="cc-note">Free to create · No token needed</div>'
        html += '</div></div>'
        $('ccMain').innerHTML = html

        $('ccBack').addEventListener('click', function () {
            stepIndex = 3
            saveState()
            render()
        })

        var nameInput = $('ccName')
        nameInput.addEventListener('input', function () {
            profile.name = nameInput.value.trim()
            $('ccNameCount').textContent = nameInput.value.length + '/20'
            saveState()
        })
        $('ccNameRefresh').addEventListener('click', function () {
            profile.name = NAME_POOL[Math.floor(Math.random() * NAME_POOL.length)]
            nameInput.value = profile.name
            $('ccNameCount').textContent = profile.name.length + '/20'
            saveState()
        })
        $('ccAgeStrip').querySelectorAll('.cc-age-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                profile.age = parseInt(chip.getAttribute('data-age'), 10)
                saveState()
                renderFinal()
                // 选中后把该 chip 滚到条带中间
                var on = document.querySelector('.cc-age-chip.on')
                if (on) on.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' })
            })
        })
        var ageOn = document.querySelector('.cc-age-chip.on')
        if (ageOn) ageOn.scrollIntoView({ block: 'nearest', inline: 'center' })

        $('ccMain').querySelectorAll('.cc-picker').forEach(function (row) {
            row.addEventListener('click', function () {
                openModal(row.getAttribute('data-picker'))
            })
        })

        var cta = $('ccCta')
        if (cta) cta.addEventListener('click', submit)
    }

    function pickerRow(type, label, value) {
        return '<button class="cc-picker" data-picker="' + type + '" type="button">'
            + '<span class="val' + (value ? ' set' : '') + '">' + (value ? esc(value) : esc(label)) + '</span>'
            + '<span class="edit">✎</span></button>'
    }

    /* ── 弹窗 ─────────────────────────────────────── */
    var MODAL_DEFS = {
        personality: { title: 'Choose Personality', multi: false, options: PERSONALITIES },
        relation: { title: 'Choose Relationship', multi: false, options: RELATIONSHIPS },
        profession: { title: 'Choose Occupation', multi: false, options: OCCUPATIONS },
        kinks: { title: 'What kinks are they into? (Max. 3)', multi: true, max: 3, searchable: true, options: KINKS },
        voice: { title: 'Choose Voice', multi: false, voice: true, options: [] },
    }

    function openModal(type) {
        currentModal = type
        var def = MODAL_DEFS[type]
        if (!def) return
        $('ccModalTitle').textContent = def.title
        $('ccModalSearchWrap').style.display = def.searchable ? 'block' : 'none'
        $('ccModalSearch').value = ''
        $('ccModalHint').style.display = def.multi ? 'block' : 'none'
        $('ccModalSave').textContent = 'Save Changes'

        if (type === 'kinks') modalDraft = (profile.kinks || []).slice()
        else if (type === 'relation') modalDraft = profile.relation ? [profile.relation] : []
        else if (type === 'profession') modalDraft = profile.profession ? [profile.profession] : []
        else if (type === 'voice') modalDraft = profile.voice ? [profile.voice] : []
        else modalDraft = profile[type] ? [profile[type]] : []

        renderModalOptions('')
        $('ccModal').style.display = 'block'
    }

    function closeModal() {
        stopSpeaking()
        $('ccModal').style.display = 'none'
        currentModal = ''
        modalDraft = []
    }

    function renderModalOptions(filter) {
        var type = currentModal
        var def = MODAL_DEFS[type]
        if (!def) return
        var opts = def.options
        if (filter) {
            var f = filter.toLowerCase()
            opts = opts.filter(function (o) { return String(o).toLowerCase().indexOf(f) >= 0 })
        }
        var imgKey = MODAL_IMG[type]
        var body = $('ccModalBody')
        // 三种形态：横排行（personality/relation，小图+文字并排）/ 文字 chip（profession/kinks）/ 单列（voice）
        body.className = 'cc-modal-body'
            + (type === 'voice' ? ' cc-voice-list' : '')
            + ((type === 'profession' || type === 'kinks') ? ' cc-chip-list' : '')
            + (imgKey ? ' cc-row-list' : '')
        var html = ''
        if (type === 'voice') {
            VOICES.forEach(function (v) {
                var on = modalDraft.indexOf(v.id) >= 0
                html += '<button class="cc-mopt' + (on ? ' on' : '') + '" data-val="' + esc(v.id) + '" type="button">'
                html += '<div class="cc-voice"><div class="vmain"><span class="vname">' + esc(v.name) + '</span><span class="vtag">' + esc(v.tag) + '</span></div>'
                html += '<span class="check">✓</span>'
                html += '<span class="cc-play" data-play="' + esc(v.id) + '" title="Preview">▶</span>'
                html += '</div></button>'
            })
        } else {
            opts.forEach(function (o) {
                var on = modalDraft.indexOf(o) >= 0
                if (imgKey) {
                    var img = IMG[imgKey][o]
                    var vis = img
                        ? '<img class="cc-row-img" src="' + img + '" alt="" loading="lazy">'
                        : '<span class="cc-row-img fallback">' + (FALLBACK_ICON[type] || '✨') + '</span>'
                    html += '<button class="cc-mopt cc-row' + (on ? ' on' : '') + '" data-val="' + esc(o) + '" type="button">'
                        + vis + '<span class="cc-row-label">' + esc(o) + '</span><span class="check">✓</span></button>'
                } else {
                    html += '<button class="cc-mopt' + (on ? ' on' : '') + '" data-val="' + esc(o) + '" type="button">' + esc(o) + '<span class="check">✓</span></button>'
                }
            })
        }
        $('ccModalBody').innerHTML = html

        $('ccModalBody').querySelectorAll('.cc-mopt').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target && e.target.getAttribute && e.target.getAttribute('data-play')) {
                    previewVoice(e.target.getAttribute('data-play'), e.target)
                    return
                }
                var val = el.getAttribute('data-val')
                var def2 = MODAL_DEFS[type]
                if (def2.multi) {
                    var i = modalDraft.indexOf(val)
                    if (i >= 0) modalDraft.splice(i, 1)
                    else {
                        if (modalDraft.length >= (def2.max || 99)) return
                        modalDraft.push(val)
                    }
                    el.classList.toggle('on')
                } else {
                    modalDraft = [val]
                    $('ccModalBody').querySelectorAll('.cc-mopt').forEach(function (x) { x.classList.remove('on') })
                    el.classList.add('on')
                }
            })
        })
    }

    /* Web Speech API 本地试听（零素材、零流量；正式音色由聊天 TTS 按 voice 字段映射） */
    var speaking = null
    function previewVoice(voiceId, btn) {
        stopSpeaking()
        if (!window.speechSynthesis) return
        var v = null
        for (var i = 0; i < VOICES.length; i++) {
            if (VOICES[i].id === voiceId) { v = VOICES[i]; break }
        }
        if (!v) return
        var u = new SpeechSynthesisUtterance(SAMPLE_LINE)
        u.pitch = Math.max(0, Math.min(2, v.pitch))
        u.rate = Math.max(0.5, Math.min(1.5, v.rate))
        var voices = window.speechSynthesis.getVoices() || []
        var female = null
        for (var j = 0; j < voices.length; j++) {
            if (FEMALE_VOICE_RE.test(voices[j].name)) { female = voices[j]; break }
        }
        if (female) u.voice = female
        u.onend = function () {
            speaking = null
            if (btn) { btn.textContent = '▶'; btn.classList.remove('playing') }
        }
        speaking = u
        if (btn) { btn.textContent = '■'; btn.classList.add('playing') }
        window.speechSynthesis.speak(u)
    }
    function stopSpeaking() {
        if (window.speechSynthesis) window.speechSynthesis.cancel()
        speaking = null
        $('ccModalBody').querySelectorAll('.cc-play').forEach(function (b) {
            b.textContent = '▶'
            b.classList.remove('playing')
        })
    }

    function saveModal() {
        var type = currentModal
        if (type === 'kinks') profile.kinks = modalDraft.slice()
        else if (type === 'relation') profile.relation = modalDraft[0] || ''
        else if (type === 'profession') profile.profession = modalDraft[0] || ''
        else if (type === 'voice') profile.voice = modalDraft[0] || ''
        else if (type === 'personality') profile.personality = modalDraft[0] || ''
        saveState()
        closeModal()
        renderFinal()
    }

    /* ── 提交 + 形象图轮询 ─────────────────────────── */
    function toast(msg, type) {
        if (window.layer && layer.msg) {
            layer.msg(msg, { icon: type === 'error' ? 2 : (type === 'success' ? 1 : 0), time: 2400 })
        } else {
            window.alert(msg)
        }
    }

    function buildPayload() {
        return {
            name: profile.name || ('My AI ' + new Date().toISOString().slice(5, 10)),
            // 直接送英文文案值：后端 source_fields 原样存，聊天 persona_desc / 形象图 prompt 直接可读
            type: profile.category,
            race: profile.race,
            age: profile.age,
            eye: profile.eye,
            hair: profile.hair,
            hairstyle: profile.hairstyle,
            body: profile.body,
            breast: profile.breast,
            clothing: 'Bikini',
            personality: profile.personality,
            profession: profile.profession,
            relation: profile.relation,
            kinks: (profile.kinks || []).join(', '),
            voice: profile.voice,
        }
    }

    function submit() {
        if (submitted) return
        if (!profile.category || !profile.race || !profile.hairstyle || !profile.hair || !profile.eye || !profile.body || !profile.breast) {
            toast('Please finish the appearance steps first', 'error')
            return
        }
        var token = localStorage.getItem('live_access_token')
        if (!token) {
            // candy 的注册墙：先登录再回来（选择已存在 localStorage，回来接着填）
            localStorage.setItem('wizard_redirect_back', '1')
            location.href = './Login.html?redirect=' + encodeURIComponent('./createCharacter.html')
            return
        }
        var cta = $('ccCta')
        if (cta) cta.disabled = true

        fetch(API + '/customRoleCreate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            body: JSON.stringify(buildPayload()),
        }).then(function (r) { return r.json() }).then(function (d) {
            if (d.code === '00000' && d.data && d.data.id) {
                personaId = d.data.id
                imageTaskId = d.data.image_task_id || 0
                submitted = true
                clearState()
                showGenerating()
                startPolling()
            } else if (d.code === '10003' || d.code === '10004') {
                localStorage.removeItem('live_access_token')
                localStorage.setItem('wizard_redirect_back', '1')
                location.href = './Login.html?redirect=' + encodeURIComponent('./createCharacter.html')
            } else {
                submitted = false
                if (cta) cta.disabled = false
                toast(d.msg || 'Create failed', 'error')
            }
        }).catch(function () {
            submitted = false
            if (cta) cta.disabled = false
            toast('Network error', 'error')
        })
    }

    function showGenerating() {
        $('ccMain').innerHTML = '<div class="cc-step"><div class="cc-done">'
            + '<div class="cc-spinner" style="margin:0 auto 16px"></div>'
            + '<h2>Bringing your AI to life…</h2>'
            + '<p>She is being generated right now — usually 1–3 minutes.<br>You can leave this page; she will be waiting in My AI.</p>'
            + '</div></div>'
        updateFab()
    }

    function showDone(imageUrl) {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
        $('ccMain').innerHTML = '<div class="cc-step"><div class="cc-done">'
            + (imageUrl ? '<img src="' + esc(imageUrl) + '" alt="">' : '')
            + '<h2>Your AI is ready ✨</h2>'
            + '<p>' + esc(profile.name || 'She') + ' is waiting for you.</p>'
            + '<div class="cc-actions" style="justify-content:center"><button class="cc-btn-next" style="max-width:none;padding:0 40px" id="ccGoChat" type="button">Chat with her now ⚡</button></div>'
            + '</div></div>'
        $('ccGoChat').addEventListener('click', function () {
            location.href = './Chat.html?id=my-' + personaId
        })
        updateFab()
    }

    function showFailed() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
        $('ccMain').innerHTML = '<div class="cc-step"><div class="cc-done">'
            + '<h2>Almost there</h2>'
            + '<p>Her portrait is taking longer than usual.<br>She is already in My AI — chat with her while her portrait finishes.</p>'
            + '<div class="cc-actions" style="justify-content:center"><button class="cc-btn-next" style="max-width:none;padding:0 40px" id="ccGoMyAi" type="button">Go to My AI</button></div>'
            + '</div></div>'
        $('ccGoMyAi').addEventListener('click', function () {
            location.href = './charactersIndex.html'
        })
        updateFab()
    }

    function startPolling() {
        var token = localStorage.getItem('live_access_token') || ''
        var tries = 0
        pollTimer = setInterval(function () {
            tries += 1
            // 20 分钟上限：到点直接引导去 My AI（worker 侧 90 分钟回收由 cron 兜底）
            if (tries > 120) { showFailed(); return }
            // 生成通道未部署（image_task_id 为空）：直接完成跳转，不空转
            if (!imageTaskId) {
                // 角色已创建成功，封面可能是旧流程人工素材 → 直接去列表页
                if (tries === 1) {
                    if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
                    location.href = './charactersIndex.html'
                }
                return
            }
            fetch(API + '/customRoleImageStatus?persona_id=' + personaId, {
                headers: { 'Authorization': 'Bearer ' + token },
                cache: 'no-store',
            }).then(function (r) { return r.json() }).then(function (d) {
                if (d.code !== '00000' || !d.data) return
                var st = d.data.status
                var url = d.data.image_url
                if (st === 'completed' && url) {
                    showDone(url)
                } else if (st === 'failed') {
                    showFailed()
                }
            }).catch(function () { /* 网络抖动：下一轮再试 */ })
        }, 10000)
    }

    /* ── 总渲染 ─────────────────────────────────── */
    function render() {
        if (stepIndex === 0) renderEntry()
        else if (stepIndex >= 1 && stepIndex <= 3) renderScreen()
        else renderFinal()
        updateFab()
    }

    function init() {
        loadState()
        sanitizeForCategory()

        var fab = $('ccFab')
        if (fab) fab.addEventListener('click', fabNext)
        var mask = $('ccModalMask')
        if (mask) mask.addEventListener('click', closeModal)
        var cancel = $('ccModalCancel')
        if (cancel) cancel.addEventListener('click', closeModal)
        var save = $('ccModalSave')
        if (save) save.addEventListener('click', saveModal)
        var search = $('ccModalSearch')
        if (search) search.addEventListener('input', function () {
            renderModalOptions(search.value.trim())
        })

        render()
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init)
    } else {
        init()
    }
})()
