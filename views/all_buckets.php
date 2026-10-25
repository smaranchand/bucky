<?php
use Bucky\Finding;

require_once __DIR__ . '/../csrf.php';

$total = count($buckets);
$counts = ['high' => 0, 'medium' => 0, 'unknown' => 0, 'none' => 0];
foreach ($buckets as $b) {
    if (isset($counts[$b['SEVERITY']])) $counts[$b['SEVERITY']]++;
}

/**
 * One plain-language headline per bucket, plus the supporting detail.
 *
 * Abbreviated tags (LIST / READ / CORS) needed decoding before they meant anything.
 * The headline says what is wrong in words; the detail line says how it was established.
 *
 * @return array{0:string,1:string,2:string} [css class, headline, detail]
 */
$status = function (array $row): array {
    if ($row['EXISTS_F'] === Finding::NO) {
        $referenced = $row['URL'] !== '-' && $row['URL'] !== '';
        return $referenced
            ? ['bad', 'Takeover possible', 'This page loads a bucket that nobody owns — you can register the name.']
            : ['idle', 'Bucket does not exist', 'Nothing was seen referencing it, so there is nothing to take over.'];
    }

    $found = [];
    if ($row['WRITABLE'] === Finding::YES) $found[] = 'anyone can upload files to it';
    if ($row['LISTABLE'] === Finding::YES) $found[] = 'anyone can list its contents';
    if ($row['READABLE'] === Finding::YES) $found[] = 'anyone can download its files';
    if ($row['CORS']     === Finding::YES) $found[] = 'it accepts requests from any website (CORS)';

    if ($found !== []) {
        $headline = $row['WRITABLE'] === Finding::YES ? 'Publicly writable'
                  : ($row['LISTABLE'] === Finding::YES || $row['READABLE'] === Finding::YES
                      ? 'Contents exposed' : 'Weak CORS policy');
        $cls = $row['CORS'] === Finding::YES && count($found) === 1 ? 'warn' : 'bad';
        return [$cls, $headline, ucfirst(implode(', ', $found)) . '.'];
    }

    // Nothing found, but say plainly what was and was not established.
    if (($row['LISTABLE'] ?? '') === Finding::UNKNOWN || ($row['CORS'] ?? '') === Finding::UNKNOWN) {
        return ['idle', 'Could not check', 'The bucket did not respond to the checks. This is not a clean result.'];
    }
    if (($row['WRITABLE'] ?? '') === Finding::UNKNOWN) {
        return ['ok', 'Nothing exposed', 'Contents are private. The upload test did not complete — see logs.'];
    }
    return ['ok', 'Nothing exposed', 'Contents are private and uploads were refused.'];
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bucky — All Buckets</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230dbce7'/><text x='16' y='23' font-family='monospace' font-size='20' font-weight='bold' text-anchor='middle' fill='%23fff'>B</text></svg>">
<style>
:root{
  --cyan:#0dbce7; --cyan-dk:#0a9cc2; --cyan-soft:#e8f8fd;
  --bg:#f4f7fa; --card:#fff; --line:#e3e9ef; --line-2:#d3dce5;
  --tx:#1c2733; --tx-2:#5b6a7d; --tx-3:#8d9aab;
  --bad:#d92d20; --bad-bg:#fef3f2; --bad-bd:#fecdc9;
  --warn:#b54708; --warn-bg:#fffaeb; --warn-bd:#fedf89;
  --ok:#067647;  --ok-bg:#ecfdf3;  --ok-bd:#abefc6;
  --idle:#5b6a7d;--idle-bg:#f2f5f8;--idle-bd:#dbe2ea;
  --mono:ui-monospace,"SF Mono",SFMono-Regular,Menlo,Consolas,monospace;
  --shadow:0 1px 2px rgba(16,24,40,.05),0 4px 12px -6px rgba(16,24,40,.10);
}
*{margin:0;padding:0;box-sizing:border-box}
body{
  background:var(--bg);color:var(--tx);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased;
}

/* header */
.top{background:linear-gradient(100deg,var(--cyan) 0%,var(--cyan-dk) 100%);color:#fff;padding:0 28px}
.top .in{max-width:1400px;margin:0 auto;height:62px;display:flex;align-items:center;gap:14px}
.top .logo{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:680;letter-spacing:-.02em}
.top .logo .m{width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.2);display:grid;place-items:center;font-size:15px;font-weight:800;backdrop-filter:blur(4px)}
.top .tag{font-size:12px;opacity:.9;padding-left:14px;margin-left:2px;border-left:1px solid rgba(255,255,255,.3)}
.top nav{margin-left:auto;display:flex;gap:6px}
.top nav a{color:#fff;text-decoration:none;font-size:13.5px;font-weight:560;padding:7px 14px;border-radius:7px;opacity:.9}
.top nav a:hover{background:rgba(255,255,255,.16);opacity:1}
.top nav a.on{background:#fff;color:var(--cyan-dk);opacity:1;font-weight:640}

.wrap{max-width:1400px;margin:0 auto;padding:22px 28px 40px}

/* summary */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:18px}
.card{background:var(--card);border:1px solid var(--line);border-radius:11px;padding:14px 17px;box-shadow:var(--shadow);cursor:pointer;text-align:left;font-family:inherit;transition:.13s}
.card:hover{border-color:var(--cyan);transform:translateY(-1px)}
.card[aria-pressed="true"]{border-color:var(--cyan);box-shadow:0 0 0 3px var(--cyan-soft),var(--shadow)}
.card .n{font-size:27px;font-weight:670;letter-spacing:-.03em;font-variant-numeric:tabular-nums;line-height:1.1}
.card .l{font-size:12px;color:var(--tx-2);font-weight:560;margin-top:3px}
.card.t-all .n{color:var(--cyan-dk)} .card.t-bad .n{color:var(--bad)}
.card.t-warn .n{color:var(--warn)} .card.t-idle .n{color:var(--idle)} .card.t-ok .n{color:var(--ok)}

.bar{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.bar input{flex:1;max-width:330px;padding:9px 13px;font-size:13.5px;font-family:inherit;border:1px solid var(--line-2);border-radius:8px;background:var(--card);color:var(--tx)}
.bar input:focus{outline:none;border-color:var(--cyan);box-shadow:0 0 0 3px var(--cyan-soft)}
.bar .n{margin-left:auto;font-size:12.5px;color:var(--tx-3);font-variant-numeric:tabular-nums}

/* table */
.panel{background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:var(--shadow)}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;font-size:11.5px;font-weight:680;letter-spacing:.04em;text-transform:uppercase;color:var(--tx-2);padding:11px 16px;background:#fafcfd;border-bottom:1px solid var(--line);white-space:nowrap}
tbody td{padding:14px 16px;border-bottom:1px solid var(--line);vertical-align:top}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:#fafdfe}
tbody tr[data-sev="high"]{box-shadow:inset 3px 0 0 var(--bad)}
tbody tr[data-sev="medium"]{box-shadow:inset 3px 0 0 var(--warn)}
tbody tr[data-sev="unknown"]{box-shadow:inset 3px 0 0 var(--idle)}

.bk{display:flex;align-items:center;gap:7px}
.bk .nm{font-family:var(--mono);font-size:13.5px;font-weight:600;word-break:break-all}
.cp{border:0;background:transparent;color:var(--tx-3);cursor:pointer;font-size:11px;padding:2px 6px;border-radius:4px;opacity:0;transition:.12s;font-family:inherit}
tr:hover .cp{opacity:1}
.cp:hover{background:var(--cyan-soft);color:var(--cyan-dk)}
.bk-sub{font-size:11.5px;color:var(--tx-3);margin-top:3px;font-variant-numeric:tabular-nums}

.src a,.src span{font-size:12.5px;color:var(--tx-2);word-break:break-all;text-decoration:none;font-family:var(--mono)}
.src a:hover{color:var(--cyan-dk);text-decoration:underline}
.src{max-width:290px}
.rg{font-family:var(--mono);font-size:12.5px;color:var(--tx-2);white-space:nowrap}

.st .h{font-size:13.5px;font-weight:640;display:flex;align-items:center;gap:7px}
.st .h::before{content:"";width:8px;height:8px;border-radius:50%;flex:none}
.st.bad .h{color:var(--bad)} .st.bad .h::before{background:var(--bad)}
.st.warn .h{color:var(--warn)} .st.warn .h::before{background:var(--warn)}
.st.ok .h{color:var(--ok)} .st.ok .h::before{background:var(--ok)}
.st.idle .h{color:var(--idle)} .st.idle .h::before{background:var(--idle)}
.st .d{font-size:12.5px;color:var(--tx-2);margin-top:4px;max-width:380px;line-height:1.45}

.poc{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;color:var(--cyan-dk);text-decoration:none;font-weight:580;background:var(--cyan-soft);padding:5px 10px;border-radius:6px;border:1px solid #bfe9f6}
.poc:hover{background:#d7f2fa}
.wbtn{background:var(--card);border:1px solid var(--line-2);color:var(--tx-2);border-radius:6px;padding:5px 11px;font-size:12px;font-weight:560;cursor:pointer;font-family:inherit;white-space:nowrap}
.wbtn:hover{border-color:var(--bad);color:var(--bad);background:var(--bad-bg)}
.nil{color:var(--tx-3)}
form.i{margin:0}

.empty{padding:60px 24px;text-align:center}
.empty h3{font-size:15px;font-weight:640;margin-bottom:6px}
.empty p{color:var(--tx-2);font-size:13.5px;max-width:420px;margin:0 auto;line-height:1.6}
.empty a{color:var(--cyan-dk)}
.note{margin-top:13px;font-size:12px;color:var(--tx-3);line-height:1.6}
.note b{color:var(--tx-2)}
.hidden{display:none!important}
@media(max-width:1100px){.src{max-width:180px}.st .d{max-width:240px}}
</style>
</head>
<body>

<header class="top">
  <div class="in">
    <div class="logo"><span class="m">B</span> Bucky</div>
    <span class="tag">S3 bucket exposure</span>
    <nav>
      <a href="/buckets" class="on">All Buckets</a>
      <a href="/check">Check Manually</a>
    </nav>
  </div>
</header>

<div class="wrap">

  <div class="cards" role="group" aria-label="Filter">
    <button class="card t-all" data-f="all" aria-pressed="true">
      <div class="n"><?= $total ?></div><div class="l">Buckets found</div>
    </button>
    <button class="card t-bad" data-f="high" aria-pressed="false">
      <div class="n"><?= $counts['high'] ?></div><div class="l">Vulnerable</div>
    </button>
    <button class="card t-warn" data-f="medium" aria-pressed="false">
      <div class="n"><?= $counts['medium'] ?></div><div class="l">Weak config</div>
    </button>
    <button class="card t-idle" data-f="unknown" aria-pressed="false">
      <div class="n"><?= $counts['unknown'] ?></div><div class="l">Could not check</div>
    </button>
    <button class="card t-ok" data-f="none" aria-pressed="false">
      <div class="n"><?= $counts['none'] ?></div><div class="l">Nothing exposed</div>
    </button>
  </div>

<?php if ($total > 0): ?>
  <div class="bar">
    <input id="q" type="search" placeholder="Search bucket, source or region&hellip;" autocomplete="off">
    <span class="n" id="n"></span>
  </div>

  <div class="panel">
    <table>
      <thead>
        <tr>
          <th>Bucket name</th>
          <th>Found on page</th>
          <th>Region</th>
          <th>What we found</th>
          <th>Proof</th>
        </tr>
      </thead>
      <tbody id="rows">
      <?php foreach ($buckets as $row): ?>
        <?php
          [$cls, $headline, $detail] = $status($row);
          $hay = strtolower($row['BUCKETNAME'] . ' ' . ($row['REGION'] ?? '') . ' ' . $row['URL'] . ' ' . $headline);
        ?>
        <tr data-sev="<?= h($row['SEVERITY']) ?>" data-s="<?= h($hay) ?>">
          <td>
            <div class="bk">
              <span class="nm"><?= h($row['BUCKETNAME']) ?></span>
              <button class="cp" data-c="<?= h($row['BUCKETNAME']) ?>" title="Copy">copy</button>
            </div>
            <?php if ((int) $row['SIGHTINGS'] > 1): ?>
              <div class="bk-sub">seen on <?= (int) $row['SIGHTINGS'] ?> pages</div>
            <?php endif; ?>
          </td>
          <td class="src">
            <?php if ($row['URL'] !== '-' && $row['URL'] !== ''): ?>
              <a href="<?= h($row['URL']) ?>" target="_blank" rel="noopener noreferrer" title="<?= h($row['URL']) ?>"><?= h($row['URL']) ?></a>
            <?php else: ?>
              <span class="nil">checked by hand</span>
            <?php endif; ?>
          </td>
          <td><span class="rg"><?= h($row['REGION'] ?: '—') ?></span></td>
          <td class="st <?= $cls ?>">
            <div class="h"><?= h($headline) ?></div>
            <div class="d"><?= h($detail) ?></div>
          </td>
          <td>
            <?php if ($row['POC'] !== '-' && $row['POC'] !== ''): ?>
              <a class="poc" href="<?= h($row['POC']) ?>" target="_blank" rel="noopener noreferrer">Open bucky.txt ↗</a>
            <?php elseif ($row['WRITABLE'] === Finding::NO): ?>
              <span class="nil" title="The upload was attempted and S3 refused it">upload refused</span>
            <?php elseif ($row['EXISTS_F'] === Finding::YES): ?>
              <form method="POST" action="/write-poc" class="i"
                    onsubmit="return confirm('Upload bucky.txt into &quot;<?= h($row['BUCKETNAME']) ?>&quot;?\n\nThis writes to infrastructure you may not own. Continue only if this bucket is in scope.');">
                <?= csrf_input() ?>
                <input type="hidden" name="bucketname" value="<?= h($row['BUCKETNAME']) ?>">
                <button type="submit" class="wbtn">Retry upload</button>
              </form>
            <?php else: ?><span class="nil">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="note">
    Findings come from unauthenticated read-only requests. Bucky also tries to upload
    <b>bucky.txt</b> automatically as proof — set <b>BUCKY_AUTO_WRITE=false</b> in <b>.env</b> to turn that off.
  </p>
<?php else: ?>
  <div class="panel">
    <div class="empty">
      <h3>No buckets found yet</h3>
      <p>Browse the web with the Bucky addon installed and buckets will show up here
         automatically, or <a href="/check">check one by hand</a>.</p>
    </div>
  </div>
<?php endif; ?>

</div>

<script>
(() => {
  const rows = [...document.querySelectorAll('#rows tr')];
  const q = document.getElementById('q'), n = document.getElementById('n');
  const cards = [...document.querySelectorAll('.card')];
  let sev = 'all';

  const apply = () => {
    const t = (q?.value || '').trim().toLowerCase();
    let shown = 0;
    for (const r of rows) {
      const ok = (sev === 'all' || r.dataset.sev === sev) && (!t || r.dataset.s.includes(t));
      r.classList.toggle('hidden', !ok);
      if (ok) shown++;
    }
    if (n) n.textContent = shown === rows.length ? `${rows.length} buckets` : `${shown} of ${rows.length}`;
  };

  cards.forEach(c => c.addEventListener('click', () => {
    sev = sev === c.dataset.f ? 'all' : c.dataset.f;
    cards.forEach(x => x.setAttribute('aria-pressed', String(x.dataset.f === sev)));
    if (sev === 'all') cards[0].setAttribute('aria-pressed', 'true');
    apply();
  }));

  q?.addEventListener('input', apply);
  document.addEventListener('click', async e => {
    const b = e.target.closest('.cp'); if (!b) return;
    try { await navigator.clipboard.writeText(b.dataset.c);
      const o = b.textContent; b.textContent = 'copied';
      setTimeout(() => b.textContent = o, 1100);
    } catch {}
  });
  apply();
})();
</script>
</body>
</html>
