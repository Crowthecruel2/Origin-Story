<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
admin_require_login();

$pdo = brighton_pdo();

$action = (string)($_GET["action"] ?? "list");
$id = (string)($_GET["id"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  admin_check_csrf();
  $postAction = (string)($_POST["action"] ?? "");

  if ($postAction === "save") {
    $uid = trim((string)($_POST["id"] ?? ""));
    $name = trim((string)($_POST["name"] ?? ""));
    $factionId = trim((string)($_POST["faction_id"] ?? ""));
    if ($uid === "" || $name === "" || $factionId === "") {
      admin_layout("Wargame Units", "<section class='card'><h2>Error</h2><p class='muted'>id, name, and faction_id are required.</p></section>");
      exit;
    }

    $headerNumbers = json_decode((string)($_POST["header_numbers_json"] ?? "[]"), true);
    $sections = json_decode((string)($_POST["sections_json"] ?? "{}"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      admin_layout("Wargame Units", "<section class='card'><h2>Error</h2><p class='muted'>Invalid JSON in header_numbers or sections.</p></section>");
      exit;
    }

    $stmt = $pdo->prepare(
      "INSERT INTO wargame_units(id,name,faction_id,starting_energy,header_numbers_json,sections_json,raw,source_page)
       VALUES(?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE
         name=VALUES(name),faction_id=VALUES(faction_id),starting_energy=VALUES(starting_energy),
         header_numbers_json=VALUES(header_numbers_json),sections_json=VALUES(sections_json),raw=VALUES(raw),source_page=VALUES(source_page)"
    );
    $stmt->execute([
      $uid,
      $name,
      $factionId,
      ($_POST["starting_energy"] ?? null) !== "" ? (int)($_POST["starting_energy"]) : null,
      json_encode($headerNumbers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ($_POST["raw"] ?? null) ?: null,
      ($_POST["source_page"] ?? null) !== "" ? (int)($_POST["source_page"]) : null,
    ]);
    header("Location: wargame-units.php");
    exit;
  }

  if ($postAction === "delete") {
    $uid = (string)($_POST["id"] ?? "");
    $pdo->prepare("DELETE FROM wargame_units WHERE id = ?")->execute([$uid]);
    header("Location: wargame-units.php");
    exit;
  }
}

if ($action === "edit") {
  $row = [
    "id" => "",
    "name" => "",
    "faction_id" => "",
    "starting_energy" => "",
    "header_numbers_json" => "[]",
    "sections_json" => "{}",
    "raw" => "",
    "source_page" => "",
  ];
  if ($id !== "") {
    $stmt = $pdo->prepare("SELECT * FROM wargame_units WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $row = $found;
  }

  $factions = $pdo->query("SELECT id,name FROM wargame_factions ORDER BY name ASC")->fetchAll();
  $opts = "<option value=''>Select…</option>";
  foreach ($factions as $f) {
    $sel = ((string)$row["faction_id"] === (string)$f["id"]) ? "selected" : "";
    $opts .= "<option {$sel} value='" . h((string)$f["id"]) . "'>" . h((string)$f["name"]) . " (" . h((string)$f["id"]) . ")</option>";
  }

  $body = "<section class='card'><h2>" . ($id ? "Edit unit (card layout)" : "Add unit (card layout)") . "</h2>
  <div class='muted'>This editor saves a structured <code>sections_json.card</code> object. You can refill units manually in this layout.</div>
  </section>";

  $body .= "<style>
    .wg-editor { display:grid; gap:0.9rem; }
    .wg-grid { display:grid; grid-template-columns: 1.1fr 0.9fr; gap:0.9rem; align-items:start; }
    @media (max-width: 1000px){ .wg-grid{ grid-template-columns: 1fr; } }
    .wg-preview-wrap{ background: #0b1220; border:1px solid var(--border); border-radius:14px; padding:0.9rem; overflow:auto; }

    .wg-card {
      width: min(760px, 100%);
      --wg-border: #0b0b0b;
      --wg-card-bg: #6b6b6b;
      --wg-header-bg: #b7ff56;
      --wg-header-text: #0a0a0a;
      --wg-accent-bg: #b45309;
      --wg-accent-text: #0b0b0b;
      --wg-table-text: #f3f4f6;
      --wg-bubble-bg: #ffffff;
      --wg-bubble-text: #0b0b0b;
      --wg-energy-bg: #efefef;
      --wg-energy-text: #0b0b0b;
      --wg-flavor-text: #101827;

      border: 8px solid var(--wg-border);
      background: var(--wg-card-bg);
      box-shadow: 0 14px 35px rgba(0,0,0,0.55);
    }
    .wg-card .header {
      background: var(--wg-header-bg);
      color: var(--wg-header-text);
      padding: 18px 18px 10px;
      border-bottom: 6px solid var(--wg-border);
      position: relative;
      display:grid;
      gap: 4px;
    }
    .wg-title { margin:0; font-size: 40px; line-height: 1; letter-spacing: 0.02em; font-weight: 1000; text-transform: uppercase; color: var(--wg-header-text); }
    .wg-sub { color: var(--wg-header-text); font-weight: 800; }
    .wg-bubbles { position:absolute; right: 12px; top: 12px; display:flex; gap: 10px; align-items:flex-start; }
    .wg-bubble { width: 44px; height: 30px; background: var(--wg-bubble-bg); border: 3px solid var(--wg-border); border-radius: 999px; display:flex; align-items:center; justify-content:center; font-weight: 1000; color: var(--wg-bubble-text); }

    .wg-stats { display:grid; grid-template-columns: 140px 1fr; gap: 14px; padding: 14px 18px; border-bottom: 6px solid var(--wg-border); }
    .wg-health { background: var(--wg-accent-bg); color: var(--wg-accent-text); border: 4px solid var(--wg-border); border-radius: 24px; padding: 14px; display:grid; gap: 6px; justify-items:center; }
    .wg-health small{ font-weight: 900; opacity: 0.9; text-transform: uppercase; }
    .wg-health strong{ font-size: 30px; }
    .wg-energy { background: var(--wg-energy-bg); color: var(--wg-energy-text); border: 4px solid var(--wg-border); border-radius: 14px; padding: 14px; display:flex; align-items:center; justify-content:center; font-weight: 900; }
    .wg-hex-row { grid-column: 1 / -1; display:flex; flex-wrap:wrap; gap: 10px; align-items:flex-start; }
    .wg-hex {
      width: 86px;
      height: 76px;
      background: var(--wg-accent-bg);
      border: 4px solid var(--wg-border);
      clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%);
      display:grid;
      align-content:center;
      justify-items:center;
      color: var(--wg-accent-text);
      font-weight: 1000;
      padding: 6px;
      text-align:center;
    }
    .wg-hex small{ font-size: 11px; text-transform: uppercase; opacity: 0.9; }
    .wg-hex span{ font-size: 18px; }
    .wg-ri {
      flex: 1 1 220px;
      min-height: 76px;
      background: var(--wg-accent-bg);
      border: 4px solid var(--wg-border);
      border-radius: 16px;
      padding: 10px 12px;
      color: var(--wg-accent-text);
      font-weight: 900;
      display:grid;
      gap: 4px;
    }
    .wg-ri .mut{ font-weight: 900; opacity: 0.85; }

    .wg-table { padding: 14px 18px; border-bottom: 6px solid var(--wg-border); }
    .wg-table table{ width:100%; border-collapse: collapse; }
    .wg-table th{ background: var(--wg-accent-bg); color: var(--wg-accent-text); border: 4px solid var(--wg-border); padding: 10px 8px; font-weight: 1000; text-transform: uppercase; font-size: 12px; letter-spacing: .08em; }
    .wg-table td{ background: var(--wg-card-bg); color: var(--wg-table-text); border: 4px solid var(--wg-border); padding: 10px 8px; font-weight: 700; }
    .wg-abilities { padding: 14px 18px; border-bottom: 6px solid var(--wg-border); }
    .wg-abilities .box{ background: var(--wg-accent-bg); border: 6px solid var(--wg-border); padding: 12px 12px; color: var(--wg-accent-text); font-weight: 900; }
    .wg-abilities pre{ margin: 8px 0 0; white-space: pre-wrap; font-weight: 800; }
    .wg-flavor{ padding: 12px 18px 18px; }
    .wg-flavor p{ margin:0; color: var(--wg-flavor-text); font-weight: 800; opacity: 0.9; }

    .wg-form-grid { display:grid; gap:0.8rem; }
    .wg-form-grid .row3 { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0.8rem; }
    @media (max-width: 900px){ .wg-form-grid .row3{ grid-template-columns: 1fr; } }
    .wg-weapons { display:grid; gap:0.6rem; }
    .wg-weapons table input{ font-family: inherit; font-size: 0.95rem; }
    .wg-weapons table td{ padding: 6px; }
    .wg-weapons .btn{ padding:.45rem .65rem; }
  </style>";

  $body .= "<section class='card wg-editor'>
  <form method='post' id='wgUnitForm' class='wg-grid' autocomplete='off'>
    <div class='wg-form-grid'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='save' />

      <div class='row'>
        <label class='muted'>ID<input name='id' value='" . h((string)$row["id"]) . "' " . ($id ? "readonly" : "") . " /></label>
        <label class='muted'>Unit name<input name='name' id='u_name' value='" . h((string)$row["name"]) . "' /></label>
      </div>

      <label class='muted'>Faction<select name='faction_id' id='u_faction'>{$opts}</select></label>

      <div class='row3'>
        <label class='muted'>Starting energy<input name='starting_energy' id='u_start_energy' value='" . h((string)($row["starting_energy"] ?? "")) . "' /></label>
        <label class='muted'>Source page<input name='source_page' id='u_source_page' value='" . h((string)($row["source_page"] ?? "")) . "' /></label>
        <label class='muted'>Subtitle / tagline<input id='u_subtitle' placeholder='One voice, Many Mouths' /></label>
      </div>

      <div class='row3'>
        <label class='muted'>Header bubble A<input id='u_bub_a' placeholder='30' /></label>
        <label class='muted'>Header bubble B<input id='u_bub_b' placeholder='1' /></label>
        <label class='muted'>Header bubble C<input id='u_bub_c' placeholder='2' /></label>
      </div>

      <div class='row3'>
        <label class='muted'>Health<input id='u_health' placeholder='30' /></label>
        <label class='muted'>Move<input id='u_move' placeholder='15' /></label>
        <label class='muted'>Armor<input id='u_armor' placeholder='1' /></label>
      </div>

      <div class='row3'>
        <label class='muted'>Dex<input id='u_dex' placeholder='+1' /></label>
        <label class='muted'>Str<input id='u_str' placeholder='+2' /></label>
        <label class='muted'>Size<input id='u_size' placeholder='3' /></label>
      </div>

      <div class='row'>
        <label class='muted'>Resistances<input id='u_res' placeholder='Psychic' /></label>
        <label class='muted'>Immunities<input id='u_imm' placeholder='Fear, Suppression' /></label>
      </div>

      <div class='card' style='background:transparent;border:1px solid var(--border)'>
        <div style='display:flex;justify-content:space-between;align-items:baseline;gap:.6rem;flex-wrap:wrap'>
          <strong>Colors</strong>
          <span class='muted'>Saved into <code>sections_json.card.colors</code></span>
        </div>
        <div class='row3'>
          <label class='muted'>Header<input type='color' id='c_header_bg' value='#b7ff56' /></label>
          <label class='muted'>Accent<input type='color' id='c_accent_bg' value='#b45309' /></label>
          <label class='muted'>Card BG<input type='color' id='c_card_bg' value='#6b6b6b' /></label>
        </div>
        <div class='row3'>
          <label class='muted'>Border<input type='color' id='c_border' value='#0b0b0b' /></label>
          <label class='muted'>Header text<input type='color' id='c_header_text' value='#0a0a0a' /></label>
          <label class='muted'>Table text<input type='color' id='c_table_text' value='#f3f4f6' /></label>
        </div>
      </div>

      <div class='card' style='background:transparent;border:1px solid var(--border)'>
        <div style='display:flex;justify-content:space-between;align-items:baseline;gap:.6rem;flex-wrap:wrap'>
          <strong>Weapons</strong>
          <button class='btn' id='addWeapon' type='button'>Add weapon</button>
        </div>
        <div class='wg-weapons'>
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Hit</th>
                <th>Keywords</th>
                <th>Damage</th>
                <th>Type</th>
                <th>Effects</th>
                <th>Range</th>
                <th></th>
              </tr>
            </thead>
            <tbody id='weaponBody'></tbody>
          </table>
          <div class='muted'>Keep it simple; you can refine formatting later.</div>
        </div>
      </div>

      <label class='muted'>Abilities (plain text)<textarea id='u_abilities' placeholder='Abilities:\n- ...'></textarea></label>
      <label class='muted'>Flavor / description (plain text)<textarea id='u_flavor' placeholder='Every congregation...'></textarea></label>

      <details class='card' style='background:transparent'>
        <summary class='muted' style='cursor:pointer;font-weight:900'>Advanced (JSON)</summary>
        <div class='muted'>These fields are what gets saved. Normally you should not edit them directly.</div>
        <label class='muted'>Header numbers (JSON array)<textarea name='header_numbers_json' id='header_numbers_json'>" . h((string)($row["header_numbers_json"] ?? "[]")) . "</textarea></label>
        <label class='muted'>Sections (JSON object)<textarea name='sections_json' id='sections_json'>" . h((string)($row["sections_json"] ?? "{}")) . "</textarea></label>
        <label class='muted'>Raw text<textarea name='raw' id='raw_text'>" . h((string)($row["raw"] ?? "")) . "</textarea></label>
      </details>

      <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
        <button class='btn primary' type='submit'>Save</button>
        <a class='btn' href='wargame-units.php'>Cancel</a>
      </div>
    </div>

    <div class='wg-preview-wrap'>
      <div class='muted' style='margin-bottom:.5rem'>Live preview</div>
      <div class='wg-card' id='wgPreview'>
        <div class='header'>
          <div class='wg-bubbles'>
            <div class='wg-bubble' data-bub='a'></div>
            <div class='wg-bubble' data-bub='b'></div>
            <div class='wg-bubble' data-bub='c'></div>
          </div>
          <h1 class='wg-title' data-field='title'>UNIT NAME</h1>
          <div class='wg-sub' data-field='subtitle'></div>
        </div>
        <div class='wg-stats'>
          <div class='wg-health'><small>Health</small><strong data-field='health'>?</strong></div>
          <div class='wg-energy'><span data-field='energy'>Starting Energy = ?</span></div>
          <div class='wg-hex-row'>
            <div class='wg-hex'><small>Move</small><span data-field='move'>?</span></div>
            <div class='wg-hex'><small>Armor</small><span data-field='armor'>?</span></div>
            <div class='wg-hex'><small>Dex</small><span data-field='dex'>?</span></div>
            <div class='wg-hex'><small>Str</small><span data-field='str'>?</span></div>
            <div class='wg-hex'><small>Size</small><span data-field='size'>?</span></div>
            <div class='wg-ri'>
              <div class='mut'>Resistances: <span data-field='res'></span></div>
              <div class='mut'>Immunities: <span data-field='imm'></span></div>
            </div>
          </div>
        </div>
        <div class='wg-table'>
          <table>
            <thead>
              <tr>
                <th>Weapons</th><th>Hit</th><th>Keywords</th><th>Damage</th><th>Damage Type</th><th>Effects</th><th>Range</th>
              </tr>
            </thead>
            <tbody id='previewWeapons'></tbody>
          </table>
        </div>
        <div class='wg-abilities'>
          <div class='box'>
            <div>Abilities:</div>
            <pre id='previewAbilities'></pre>
          </div>
        </div>
        <div class='wg-flavor'>
          <p id='previewFlavor'></p>
        </div>
      </div>
    </div>
  </form>
  </section>";

  $body .= "<script>
  (function(){
    const form = document.getElementById('wgUnitForm');
    const sectionsEl = document.getElementById('sections_json');
    const headerEl = document.getElementById('header_numbers_json');
    const rawEl = document.getElementById('raw_text');

    const preview = document.getElementById('wgPreview');
    const weaponBody = document.getElementById('weaponBody');
    const previewWeapons = document.getElementById('previewWeapons');
    const previewAbilities = document.getElementById('previewAbilities');
    const previewFlavor = document.getElementById('previewFlavor');

    const get = (id) => document.getElementById(id);
    const elName = get('u_name');
    const elSubtitle = get('u_subtitle');
    const elStartEnergy = get('u_start_energy');
    const elHealth = get('u_health');
    const elMove = get('u_move');
    const elArmor = get('u_armor');
    const elDex = get('u_dex');
    const elStr = get('u_str');
    const elSize = get('u_size');
    const elRes = get('u_res');
    const elImm = get('u_imm');
    const elAbilities = get('u_abilities');
    const elFlavor = get('u_flavor');
    const elBubA = get('u_bub_a');
    const elBubB = get('u_bub_b');
    const elBubC = get('u_bub_c');

    const cHeaderBg = get('c_header_bg');
    const cAccentBg = get('c_accent_bg');
    const cCardBg = get('c_card_bg');
    const cBorder = get('c_border');
    const cHeaderText = get('c_header_text');
    const cTableText = get('c_table_text');

    const safeParse = (text, fallback) => {
      try { return JSON.parse(text || ''); } catch { return fallback; }
    };

    const normalizeWeapons = (arr) => Array.isArray(arr) ? arr.filter(Boolean).map(w => ({
      name: String(w.name ?? ''),
      hit: String(w.hit ?? ''),
      keywords: String(w.keywords ?? ''),
      damage: String(w.damage ?? ''),
      type: String(w.type ?? ''),
      effects: String(w.effects ?? ''),
      range: String(w.range ?? '')
    })) : [];

    const loadExisting = () => {
      const sections = safeParse(sectionsEl.value, {});
      const header = safeParse(headerEl.value, []);
      const card = (sections && sections.card) ? sections.card : {};
      const colors = (card && card.colors) ? card.colors : {};

      elSubtitle.value = card.subtitle ?? '';
      elHealth.value = card.stats?.health ?? '';
      elMove.value = card.stats?.move ?? '';
      elArmor.value = card.stats?.armor ?? '';
      elDex.value = card.stats?.dex ?? '';
      elStr.value = card.stats?.str ?? '';
      elSize.value = card.stats?.size ?? '';
      elRes.value = card.resistances ?? '';
      elImm.value = card.immunities ?? '';
      elAbilities.value = card.abilities ?? '';
      elFlavor.value = card.flavor ?? '';
      elBubA.value = header?.[0] ?? '';
      elBubB.value = header?.[1] ?? '';
      elBubC.value = header?.[2] ?? '';

      cHeaderBg.value = colors.headerBg ?? cHeaderBg.value;
      cAccentBg.value = colors.accentBg ?? cAccentBg.value;
      cCardBg.value = colors.cardBg ?? cCardBg.value;
      cBorder.value = colors.borderColor ?? cBorder.value;
      cHeaderText.value = colors.headerText ?? cHeaderText.value;
      cTableText.value = colors.tableText ?? cTableText.value;

      const weapons = normalizeWeapons(card.weapons);
      weaponBody.innerHTML = '';
      weapons.forEach(addWeaponRow);
      if (!weapons.length) addWeaponRow();
    };

    const addWeaponRow = (w) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td><input data-k='name' value='\${(w?.name ?? '').replace(/\"/g,'&quot;')}' /></td>
        <td><input data-k='hit' value='\${(w?.hit ?? '').replace(/\"/g,'&quot;')}' style='width:90px' /></td>
        <td><input data-k='keywords' value='\${(w?.keywords ?? '').replace(/\"/g,'&quot;')}' /></td>
        <td><input data-k='damage' value='\${(w?.damage ?? '').replace(/\"/g,'&quot;')}' style='width:120px' /></td>
        <td><input data-k='type' value='\${(w?.type ?? '').replace(/\"/g,'&quot;')}' style='width:120px' /></td>
        <td><input data-k='effects' value='\${(w?.effects ?? '').replace(/\"/g,'&quot;')}' /></td>
        <td><input data-k='range' value='\${(w?.range ?? '').replace(/\"/g,'&quot;')}' style='width:110px' /></td>
        <td><button class='btn danger' type='button' data-act='del'>X</button></td>
      `;
      row.querySelector('[data-act=\"del\"]').addEventListener('click', () => {
        row.remove();
        updatePreview();
      });
      row.querySelectorAll('input').forEach(i => i.addEventListener('input', updatePreview));
      weaponBody.appendChild(row);
      updatePreview();
    };

    document.getElementById('addWeapon')?.addEventListener('click', () => addWeaponRow());

    const readWeapons = () => {
      const rows = Array.from(weaponBody.querySelectorAll('tr'));
      return rows.map(r => {
        const getVal = (k) => (r.querySelector(`[data-k=\"\${k}\"]`)?.value ?? '').trim();
        return {
          name: getVal('name'),
          hit: getVal('hit'),
          keywords: getVal('keywords'),
          damage: getVal('damage'),
          type: getVal('type'),
          effects: getVal('effects'),
          range: getVal('range'),
        };
      }).filter(w => w.name || w.damage || w.effects);
    };

    const buildPayload = () => {
      const header = [elBubA.value.trim(), elBubB.value.trim(), elBubC.value.trim()].filter(Boolean);
      const weapons = readWeapons();
      const colors = {
        headerBg: cHeaderBg.value,
        accentBg: cAccentBg.value,
        cardBg: cCardBg.value,
        borderColor: cBorder.value,
        headerText: cHeaderText.value,
        tableText: cTableText.value,
      };
      const card = {
        subtitle: elSubtitle.value.trim(),
        stats: {
          health: elHealth.value.trim(),
          move: elMove.value.trim(),
          armor: elArmor.value.trim(),
          dex: elDex.value.trim(),
          str: elStr.value.trim(),
          size: elSize.value.trim(),
        },
        resistances: elRes.value.trim(),
        immunities: elImm.value.trim(),
        weapons,
        abilities: elAbilities.value,
        flavor: elFlavor.value,
        colors,
      };
      const sections = { card };

      headerEl.value = JSON.stringify(header);
      sectionsEl.value = JSON.stringify(sections);
      rawEl.value = [
        elName.value,
        card.subtitle,
        'Starting Energy=' + (elStartEnergy.value ?? ''),
        'Health=' + card.stats.health,
        'Move=' + card.stats.move,
        'Armor=' + card.stats.armor,
        'Dex=' + card.stats.dex,
        'Str=' + card.stats.str,
        'Size=' + card.stats.size,
        'Resistances=' + card.resistances,
        'Immunities=' + card.immunities,
        weapons.map(w => w.name + ' ' + (w.damage||'') + ' ' + (w.effects||'')).join(' | '),
        (card.abilities || '').replace(/\\s+/g,' ').slice(0, 800),
        (card.flavor || '').replace(/\\s+/g,' ').slice(0, 800),
      ].filter(Boolean).join('\\n');
    };

    const setText = (sel, val) => {
      const node = preview.querySelector(sel);
      if (node) node.textContent = val ?? '';
    };

    const updatePreview = () => {
      preview.style.setProperty('--wg-header-bg', cHeaderBg.value || '#b7ff56');
      preview.style.setProperty('--wg-accent-bg', cAccentBg.value || '#b45309');
      preview.style.setProperty('--wg-card-bg', cCardBg.value || '#6b6b6b');
      preview.style.setProperty('--wg-border', cBorder.value || '#0b0b0b');
      preview.style.setProperty('--wg-header-text', cHeaderText.value || '#0a0a0a');
      preview.style.setProperty('--wg-table-text', cTableText.value || '#f3f4f6');

      setText('[data-field=\"title\"]', (elName.value || 'UNIT NAME').toUpperCase());
      setText('[data-field=\"subtitle\"]', elSubtitle.value ? '\"' + elSubtitle.value + '\"' : '');
      setText('[data-field=\"energy\"]', 'Starting Energy = ' + (elStartEnergy.value || '?'));
      setText('[data-field=\"health\"]', elHealth.value || '?');
      setText('[data-field=\"move\"]', elMove.value || '?');
      setText('[data-field=\"armor\"]', elArmor.value || '?');
      setText('[data-field=\"dex\"]', elDex.value || '?');
      setText('[data-field=\"str\"]', elStr.value || '?');
      setText('[data-field=\"size\"]', elSize.value || '?');
      setText('[data-field=\"res\"]', elRes.value || '');
      setText('[data-field=\"imm\"]', elImm.value || '');
      preview.querySelector('[data-bub=\"a\"]').textContent = elBubA.value || '';
      preview.querySelector('[data-bub=\"b\"]').textContent = elBubB.value || '';
      preview.querySelector('[data-bub=\"c\"]').textContent = elBubC.value || '';

      previewWeapons.innerHTML = '';
      const weapons = readWeapons();
      if (!weapons.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan=\"7\" style=\"opacity:.75\">No weapons yet</td>`;
        previewWeapons.appendChild(tr);
      } else {
        weapons.forEach(w => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>\${escapeHtml(w.name)}</td>
            <td>\${escapeHtml(w.hit)}</td>
            <td>\${escapeHtml(w.keywords)}</td>
            <td>\${escapeHtml(w.damage)}</td>
            <td>\${escapeHtml(w.type)}</td>
            <td>\${escapeHtml(w.effects)}</td>
            <td>\${escapeHtml(w.range)}</td>
          `;
          previewWeapons.appendChild(tr);
        });
      }

      previewAbilities.textContent = elAbilities.value || '';
      previewFlavor.textContent = elFlavor.value || '';
    };

    const escapeHtml = (s) => String(s ?? '').replace(/[&<>\"']/g, (c) => {
      if (c === '&') return '&amp;';
      if (c === '<') return '&lt;';
      if (c === '>') return '&gt;';
      if (c === '\"') return '&quot;';
      return '&#39;';
    });

    [elName,elSubtitle,elStartEnergy,elHealth,elMove,elArmor,elDex,elStr,elSize,elRes,elImm,elAbilities,elFlavor,elBubA,elBubB,elBubC,
     cHeaderBg,cAccentBg,cCardBg,cBorder,cHeaderText,cTableText]
      .forEach(el => el && el.addEventListener('input', updatePreview));

    form.addEventListener('submit', () => {
      buildPayload();
    });

    loadExisting();
    updatePreview();
  })();
  </script>";

  if ($id) {
    $body .= "<form method='post' onsubmit='return confirm(\"Delete this unit?\")'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='delete' />
      <input type='hidden' name='id' value='" . h($id) . "' />
      <button class='btn danger' type='submit'>Delete</button>
    </form>";
  }
  admin_layout("Wargame Units", $body);
  exit;
}

$q = trim((string)($_GET["q"] ?? ""));
if ($q !== "") {
  $stmt = $pdo->prepare(
    "SELECT u.id,u.name,u.faction_id,u.source_page,f.name faction_name
     FROM wargame_units u JOIN wargame_factions f ON f.id=u.faction_id
     WHERE u.id LIKE ? OR u.name LIKE ?
     ORDER BY f.name ASC, u.name ASC LIMIT 500"
  );
  $like = "%" . $q . "%";
  $stmt->execute([$like, $like]);
  $rows = $stmt->fetchAll();
} else {
  $rows = $pdo->query(
    "SELECT u.id,u.name,u.faction_id,u.source_page,f.name faction_name
     FROM wargame_units u JOIN wargame_factions f ON f.id=u.faction_id
     ORDER BY f.name ASC, u.name ASC LIMIT 500"
  )->fetchAll();
}

$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>Wargame Units</h2>
  <a class='btn primary' href='wargame-units.php?action=edit'>Add unit</a>
</div>
<form method='get' style='display:flex;gap:.6rem;flex-wrap:wrap;align-items:center'>
  <input name='q' placeholder='Search id/name...' value='" . h($q) . "' />
  <button class='btn' type='submit'>Search</button>
  <a class='btn' href='wargame-units.php'>Clear</a>
</form>
<div class='muted'>Showing up to 500 rows.</div>
<table><thead><tr><th>ID</th><th>Name</th><th>Faction</th><th>PDF page</th></tr></thead><tbody>";
foreach ($rows as $r) {
  $body .= "<tr>
    <td><a href='wargame-units.php?action=edit&id=" . rawurlencode((string)$r["id"]) . "'>" . h((string)$r["id"]) . "</a></td>
    <td>" . h((string)$r["name"]) . "</td>
    <td class='muted'>" . h((string)$r["faction_name"]) . "</td>
    <td class='muted'>" . h((string)($r["source_page"] ?? "")) . "</td>
  </tr>";
}
$body .= "</tbody></table></section>";

admin_layout("Wargame Units", $body);
