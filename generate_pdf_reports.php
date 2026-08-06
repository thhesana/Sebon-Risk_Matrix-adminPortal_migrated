<?php
/**
 * AML/CFT Risk Assessment Report Generator
 *
 * Produces one HTML report per eligible Reporting Entity, packaged as a ZIP.
 *
 * Report contents:
 *   PART A – Annex 1 (submitted by reporting entity)
 *       A1. Customer Risk – actual transaction amounts by customer type
 *       A2. PEPs Risk – actual amounts by PEPs category
 *       A3. Delivery Channel – actual amounts by channel type
 *       A4. Geographic Zone – actual amounts by zone
 *       A5. Total Assets declared
 *
 *   PART B – Inherent Risk Calculation (derived from Part A)
 *       B1. Per-activity risk dimension scores and inherent risk
 *
 *   PART C – Annex 2 (submitted by entity, graded by SEBON)
 *       C1. Full questionnaire: question, MP status, MP remarks,
 *           SEBON remarks, SEBON rating, reviewing SEBON user
 *       C2. RAS component score summary
 *
 *   PART D – Final Risk Calculations
 *       D1. Composite Risk by activity
 *       D2. Structural Risk breakdown
 *       D3. Net Profile Risk
 *
 *   Appendix – Methodology reference
 *
 * Eligibility: Only participants where overall composite risk > 0.
 * This requires inherent risk > 0 (Annex 1 data exists) AND RAS > 0
 * (Annex 2 has been graded by SEBON and saved to RiskControlsAndMitigants).
 * Identical gate to COMPOSITE_RISK_REPORT.php.
 */

ob_start();
include 'db.php';
include 'header.php';

// =========================================================
// CALCULATION FUNCTIONS
// =========================================================

function avgNonZero(array $vals) {
    $nz = array_filter($vals, function($v) { return $v > 0; });
    return empty($nz) ? 0.0 : round(array_sum($nz) / count($nz), 4);
}

function weightedRiskScore(array $vals, array $weights) {
    $total = array_sum($vals);
    if ($total == 0) return 0.0;
    $s = 0.0;
    foreach ($vals as $i => $v) $s += ($v / $total) * $weights[$i];
    return round($s, 4);
}

function totalRAS($cg, $pp, $rm, $ic, $cf, $tr, $rr) {
    $w = [30, 10, 20, 15, 15, 5, 5];
    $v = [(float)$cg, (float)$pp, (float)$rm, (float)$ic, (float)$cf, (float)$tr, (float)$rr];
    $ws = 0.0; $wt = 0;
    foreach ($v as $i => $val) {
        if ($val > 0) { $ws += $val * $w[$i]; $wt += $w[$i]; }
    }
    return $wt > 0 ? round($ws / $wt, 4) : 0.0;
}

function structuralRisk($row) {
    if (!$row) return 0.0;
    $ta = isset($row['TotalAssets'])    ? (float)$row['TotalAssets']    : 0.0;
    $fg = isset($row['FinancialGroup']) ? (float)$row['FinancialGroup'] : 0.0;
    return round($ta * 0.90 + $fg * 0.10, 4);
}

function netProfileRisk($structural, $inherent) {
    if ($inherent == 0) return 0.0;
    return round(0.25 * $structural + 0.75 * $inherent, 4);
}

function fv($data, $section, $subId, $field) {
    return (isset($data[$section][$subId][$field]) && is_numeric($data[$section][$subId][$field]))
        ? (float)$data[$section][$subId][$field] : 0.0;
}

function riskCat($r) {
    $r = (float)$r;
    if ($r <= 0)    return 'N/A';
    if ($r <= 1.50) return 'Very Low';
    if ($r <= 2.50) return 'Low';
    if ($r <= 3.50) return 'Medium';
    if ($r <= 4.00) return 'High';
    return 'Very High';
}

function catCls($cat) {
    if ($cat === 'Very Low')  return 'c-vl';
    if ($cat === 'Low')       return 'c-lo';
    if ($cat === 'Medium')    return 'c-me';
    if ($cat === 'High')      return 'c-hi';
    if ($cat === 'Very High') return 'c-vh';
    return 'c-na';
}

function fmt($v, $dec = 2) {
    return ($v == 0) ? '<span class="na">-</span>' : number_format((float)$v, $dec);
}

// =========================================================
// DATABASE FETCH FUNCTIONS
// =========================================================

function fetchQuant($conn, $mpId, $fyId, $type) {
    $data = [];
    if ($type === 'customer_risk')         $subIds = [1, 2, 3, 4];
    elseif ($type === 'peps_risk')         $subIds = [6, 5];
    elseif ($type === 'delivery_channel')  $subIds = [7, 8];
    elseif ($type === 'geographic_zone')   $subIds = [9, 10, 11, 12];
    else                                    $subIds = [];

    foreach ($subIds as $sid) {
        $q = [
            'stock_broker'  => "SELECT ShareTransaction,CommercialDebentureBondTransaction,
                                GovernmentBondTransaction,MutualFundTransaction,MarginService
                                FROM StockBrokerService
                                WHERE MarketParticipant_id=? AND FiscalYear_id=? AND SubMaster4table_id=?",
            'issue_sales'   => "SELECT IPO_GeneralPublic,IPO_Employees,IPO_LocalPeople,IPO_PF_CIT_Others,
                                FPO,PrivatePlacement,OfferDocument,RightShare,AuctionShare
                                FROM IssueAndSalesManagementService
                                WHERE MarketParticipant_id=? AND FiscalYear_id=? AND SubMaster4table_id=?",
            'portfolio'     => "SELECT Discretionary,NonDiscretionary,Advisory,ReturnGuarantee
                                FROM PortfolioManagementService
                                WHERE MarketParticipant_id=? AND FiscalYear_id=? AND SubMaster4table_id=?",
            'business_risk' => "SELECT DematAccountCount
                                FROM BusinessRiskOtherServices
                                WHERE MarketParticipant_id=? AND FiscalYear_id=? AND SubMaster4table_id=?"
        ];
        foreach ($q as $sec => $sql) {
            $s = sqlsrv_query($conn, $sql, [$mpId, $fyId, $sid]);
            if ($s && $r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC))
                $data[$sec][$sid] = $r;
        }
    }
    return $data;
}

function fetchQuali($conn, $mpId, $fyId) {
    $s = sqlsrv_query($conn, "SELECT * FROM RiskControlsAndMitigants
         WHERE MarketParticipant_id=? AND FiscalYear_id=?", [$mpId, $fyId]);
    return ($s && $r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC)) ? $r : null;
}

function fetchStructural($conn, $mpId, $fyId) {
    $s = sqlsrv_query($conn, "SELECT TotalAssets,FinancialGroup FROM StructuralRisk
         WHERE MarketParticipant_id=? AND FiscalYear_id=?", [$mpId, $fyId]);
    return ($s && $r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC)) ? $r : null;
}

function fetchTotalAssets($conn, $mpId, $fyId) {
    $s = sqlsrv_query($conn,
        "SELECT TotalAssets FROM InformationRegardingTotalAssets
         WHERE MarketParticipant_id=? AND FiscalYear_id=?", [$mpId, $fyId]);
    if ($s && $r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC))
        return isset($r['TotalAssets']) ? (float)$r['TotalAssets'] : null;
    return null;
}

/**
 * Returns Annex 2 questionnaire rows grouped by section.
 * Includes MP answers, SEBON remarks, SEBON rating, and reviewer username.
 */
function fetchAnnex2($conn, $mpId, $fyId) {
    $sql = "
        SELECT
            a.[Annex2_F3Ques_Ans_collection_id],
            a.[Annex2_F3Questions_id],
            a.[Annex2_F3Ques_Ans_collection_status],
            a.[Annex2_F3Ques_Ans_collection_RemarksByMP],
            a.[Annex2_F3Ques_Ans_collection_RemarksBySebon],
            a.[Annex2_F3Ques_Ans_collection_RatingBySebon],
            a.[reviewedBySebon_user_id],
            u.[username]  AS reviewer_username,
            q.[Annex2_F3QuestionsList],
            q.[Annex2_F3RelatedDirectives],
            m.[Annex2_F3Master_id],
            m.[Annex2_F3Master_Section]
        FROM [RiskMatrix_AML].[dbo].[Annex2_F3Ques_Ans_collection] a
        INNER JOIN [RiskMatrix_AML].[dbo].[Annex2_F3Questions] q
            ON a.[Annex2_F3Questions_id] = q.[Annex2_F3Questions_id]
        INNER JOIN [RiskMatrix_AML].[dbo].[Annex2_F3Master] m
            ON q.[Annex2_F3Master_id] = m.[Annex2_F3Master_id]
        LEFT JOIN [RiskMatrix_AML].[dbo].[Users_detail] u
            ON a.[reviewedBySebon_user_id] = u.[user_id]
        WHERE a.[MarketParticipant_id] = ? AND a.[FiscalYear_id] = ?
        ORDER BY m.[Annex2_F3Master_id], q.[Annex2_F3Questions_id]";

    $s  = sqlsrv_query($conn, $sql, [$mpId, $fyId]);
    $out = [];
    if ($s) {
        while ($r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC)) {
            $sid = (int)$r['Annex2_F3Master_id'];
            if (!isset($out[$sid]))
                $out[$sid] = ['name' => $r['Annex2_F3Master_Section'], 'rows' => []];
            $out[$sid]['rows'][] = $r;
        }
    }
    return $out;
}

// =========================================================
// CONFIGURATION: LABELS AND WEIGHTS
// =========================================================

$ACTIVITY_DEFS = [
    'stock_broker' => [
        'label'  => '1. Stock Broker Service',
        'fields' => [
            'ShareTransaction'                  => 'Share Transaction',
            'CommercialDebentureBondTransaction' => 'Commercial Debenture / Bond Transaction',
            'GovernmentBondTransaction'          => 'Government Bond Transaction',
            'MutualFundTransaction'              => 'Mutual Fund Transaction',
            'MarginService'                      => 'Margin Service',
        ],
        'sebon_weights' => [55, 10, 10, 20, 5],
    ],
    'issue_sales' => [
        'label'  => '2. Issue and Sales Management Service',
        'fields' => [
            'IPO_GeneralPublic'  => 'IPO – General Public',
            'IPO_Employees'      => 'IPO – Employees',
            'IPO_LocalPeople'    => 'IPO – Local People',
            'IPO_PF_CIT_Others'  => 'IPO – PF / CIT / Others',
            'FPO'                => 'Further Public Offering (FPO)',
            'PrivatePlacement'   => 'Private Placement',
            'OfferDocument'      => 'Offer Document',
            'RightShare'         => 'Right Share Issue',
            'AuctionShare'       => 'Auction Share',
        ],
        'sebon_weights' => [5, 25, 10, 20, 15, 5, 5, 5, 10],
    ],
    'portfolio' => [
        'label'  => '3. Portfolio Management Service',
        'fields' => [
            'Discretionary'    => 'Discretionary',
            'NonDiscretionary' => 'Non-Discretionary',
            'Advisory'         => 'Advisory',
            'ReturnGuarantee'  => 'Return Guarantee',
        ],
        'sebon_weights' => [60, 10, 10, 20],
    ],
    'business_risk' => [
        'label'  => '4. Other Services (Demat)',
        'fields' => ['DematAccountCount' => 'Demat Account (count)'],
        'sebon_weights' => [40],
    ],
];

$SEBON_WT_MAP = [];
foreach ($ACTIVITY_DEFS as $sec => $def) {
    $keys = array_keys($def['fields']);
    foreach ($keys as $i => $k)
        $SEBON_WT_MAP[$sec][$k] = $def['sebon_weights'][$i] ?? 0;
}

$RAS_COMPONENTS = [
    'CorporateGovernance'    => ['Corporate Governance',         30],
    'PoliciesProcedures'     => ['Policies and Procedures',      10],
    'RiskManagement'         => ['Risk Management',              20],
    'InternalControls'       => ['Internal Controls',            15],
    'ComplianceFunction'     => ['Compliance Function',          15],
    'Training'               => ['Training',                      5],
    'ReportingRecordKeeping' => ['Reporting and Record Keeping',  5],
];

// Maps Annex2_F3Master_id to RiskControlsAndMitigants column
$ANNEX2_SECTION_MAP = [
    1 => 'CorporateGovernance',
    2 => 'PoliciesProcedures',
    3 => 'RiskManagement',
    4 => 'InternalControls',
    5 => 'ComplianceFunction',
    6 => 'Training',
    7 => 'ReportingRecordKeeping',
];

// =========================================================
// REPORT BUILDER
// =========================================================

function buildReport($d, $fyName, $genDate) {
    global $ACTIVITY_DEFS, $SEBON_WT_MAP, $RAS_COMPONENTS, $ANNEX2_SECTION_MAP;

    $mpName = htmlspecialchars($d['mp_name']);
    $cr     = $d['customer_risk'];
    $pe     = $d['peps_risk'];
    $dc     = $d['delivery_channel'];
    $gz     = $d['geographic_zone'];
    $qual   = $d['qualitative'];
    $ann2   = $d['annex2'];
    $acts   = $d['activities'];

    $ras    = $d['ras'];
    $str    = $d['structural_risk'];
    $strRaw = $d['structural_raw'];
    $inh    = $d['inherent_risk'];
    $cmp    = $d['composite_risk'];
    $net    = $d['net_profile_risk'];
    $ta     = $d['total_assets'];

    // Risk weights for display
    $CRW = [1, 3, 4, 5]; // customer risk subIds: 1,2,3,4
    $PEW = [4, 5];        // peps subIds: 6,5
    $DCW = [3, 5];        // delivery subIds: 7,8
    $GZW = [1, 2, 3, 4];  // geo subIds: 9,10,11,12

    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AML/CFT Risk Report – <?= $mpName ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;font-size:10.5pt;color:#111;background:#fff;
     padding:30px 38px;line-height:1.5}

/* HEADER */
.rh{border-bottom:3px double #222;padding-bottom:12px;margin-bottom:18px}
.rh .badge{font-size:8pt;letter-spacing:.6px;text-transform:uppercase;color:#555;margin-bottom:5px}
.rh h1{font-size:17pt;margin-bottom:3px}
.rh h2{font-size:12pt;font-weight:normal;color:#444}
.rh .meta{margin-top:9px;font-size:9pt;color:#666;display:flex;gap:30px;flex-wrap:wrap}
.rh .meta span strong{color:#111}
.conf{float:right;font-size:8pt;border:1px solid #aaa;padding:2px 9px;color:#777;margin-top:3px}

/* SECTION HEADINGS */
.sh{background:#222;color:#fff;padding:6px 12px;font-size:10.5pt;font-weight:bold;
    margin:22px 0 8px;letter-spacing:.2px}
.ssh{background:#555;color:#fff;padding:3px 12px;font-size:9.5pt;font-weight:bold;
     margin:14px 0 5px}
.note{font-size:8.5pt;color:#666;margin:3px 0 12px;font-style:italic}

/* EXECUTIVE SUMMARY */
.ex-grid{display:grid;grid-template-columns:repeat(5,1fr);border:1px solid #999;margin-bottom:3px}
.ex-cell{padding:10px 12px;border-right:1px solid #999}
.ex-cell:last-child{border-right:none}
.ex-cell .ecl{font-size:8pt;color:#666;text-transform:uppercase;letter-spacing:.3px;margin-bottom:3px}
.ex-cell .ecv{font-size:17pt;font-weight:bold}
.ex-cell .ecc{font-size:8.5pt;color:#555;margin-top:2px}
.net-row{border:1px solid #999;border-top:none;padding:8px 12px;background:#f5f5f5;
         font-size:10pt;margin-bottom:14px}

/* TABLES */
table{width:100%;border-collapse:collapse;font-size:9pt;margin:5px 0 14px}
th,td{border:1px solid #bbb;padding:4px 6px;text-align:center;vertical-align:middle}
thead th{background:#333;color:#fff;font-size:8.5pt}
.th2{background:#555;color:#fff;font-size:8pt}
.th3{background:#777;color:#fff;font-size:8pt}
.tr-sec{background:#ddd;font-weight:bold;text-align:left;padding-left:8px;font-size:9.5pt}
.tr-act{text-align:left;padding-left:22px;background:#fafafa}
.tr-tot{background:#e0e0e0;font-weight:bold}
.tr-fin{background:#222;color:#fff;font-weight:bold}
.td-wt{background:#eee;font-weight:bold;font-size:8.5pt}
.td-rs{font-weight:bold}
.na{color:#bbb;font-style:italic}

/* RISK CATEGORY SHADING */
.c-vl{background:#d6ead6}
.c-lo{background:#e8f2e8}
.c-me{background:#fff3d0}
.c-hi{background:#ffe0d0}
.c-vh{background:#f7c8c8}
.c-na{color:#aaa}

/* ANNEX 2 */
.a2-sh{background:#444;color:#fff;padding:4px 10px;font-size:9pt;font-weight:bold;margin:10px 0 0}
.a2t td{font-size:8.5pt;vertical-align:top;text-align:left;padding:4px 5px}
.a2t th{font-size:8pt}
.a2t .c-sn{text-align:center;width:26px;background:#f0f0f0;font-size:8pt}
.a2t .c-q {width:22%}
.a2t .c-dr{width:7%;text-align:center;color:#555;font-style:italic;font-size:8pt}
.a2t .c-st{width:6%;text-align:center;font-weight:bold;font-size:9pt}
.a2t .c-mr{width:21%;color:#333}
.a2t .c-sr{width:21%;color:#333}
.a2t .c-rt{width:8%;text-align:center;font-weight:bold}
.a2t .c-rv{width:11%;text-align:center;font-size:8pt;color:#555}
.a2-avg{background:#e0e0e0;font-weight:bold;font-size:9pt}
.sy{color:#1a6b1a;font-weight:bold}
.sn{color:#8b1111;font-weight:bold}

/* KV LIST */
.kv{border:1px solid #ccc;margin-bottom:12px}
.kvr{display:flex;border-bottom:1px solid #e0e0e0}
.kvr:last-child{border-bottom:none}
.kvk{width:46%;padding:5px 8px;background:#f6f6f6;color:#444;font-size:9.5pt}
.kvv{width:54%;padding:5px 8px;font-weight:bold;font-size:9.5pt}

/* METHODOLOGY BOX */
.mbox{border:1px solid #bbb;padding:10px 14px;background:#f9f9f9;font-size:8.5pt;line-height:1.75}
.mbox table th{background:#444;color:#fff}
.mbox table{font-size:8.5pt;margin:6px 0 0;width:auto}
.mbox table td,.mbox table th{padding:3px 8px}

/* FOOTER */
.rf{margin-top:26px;padding-top:9px;border-top:1px solid #ccc;font-size:8.5pt;color:#777;
    display:flex;justify-content:space-between}

@media print{
  body{padding:10px 14px}
  .sh,.ssh{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  table{page-break-inside:auto}tr{page-break-inside:avoid}
  thead{display:table-header-group}
}
</style>
</head>
<body>

<!-- ======================================================
     REPORT HEADER
     ====================================================== -->
<div class="rh" style="text-align: center;">

  <div class="badge">AML/CFT Risk Calculation Matrix Report</div>
  <h1><?= $mpName ?></h1>

  <div class="meta" style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
    <span><strong>Fiscal Year:</strong> <?= htmlspecialchars($fyName) ?></span>
    <span><strong>Report Generation Date:</strong> <?= $genDate ?></span>
    <span><strong>Report Status:</strong> Final (Net Profile Risk Calculated)</span>
  </div>
</div>

<!-- ======================================================
     EXECUTIVE SUMMARY
     ====================================================== -->
<div class="sh">Executive Summary</div>

<div class="ex-grid">
  <div class="ex-cell">
    <div class="ecl">Total Assets Declared (Rs.)</div>
    <div class="ecv" style="font-size:12pt"><?= $ta !== null ? number_format($ta, 2) : '<span class="na">N/A</span>' ?></div>
  </div>
  <div class="ex-cell">
    <div class="ecl">Structural Risk</div>
    <div class="ecv"><?= number_format($str, 2) ?></div>
    <div class="ecc"><?= riskCat($str) ?></div>
  </div>
  <div class="ex-cell">
    <div class="ecl">Inherent Risk (Quantitative)</div>
    <div class="ecv"><?= number_format($inh, 2) ?></div>
    <div class="ecc"><?= riskCat($inh) ?></div>
  </div>
  <div class="ex-cell">
    <div class="ecl">RAS Score (Qualitative)</div>
    <div class="ecv"><?= number_format($ras, 2) ?></div>
    <div class="ecc"><?= riskCat($ras) ?></div>
  </div>
  <div class="ex-cell">
    <div class="ecl">Overall Composite Risk</div>
    <div class="ecv"><?= number_format($cmp, 2) ?></div>
    <div class="ecc"><strong><?= riskCat($cmp) ?></strong></div>
  </div>
</div>
<div class="net-row">
  <strong>Net Profile Risk: <?= number_format($net, 2) ?> – <?= riskCat($net) ?></strong>
  &nbsp;&nbsp;&nbsp;
  <span style="font-size:9pt;color:#555">
    = (25% &times; Structural <?= number_format($str,2) ?>) + (75% &times; Inherent <?= number_format($inh,2) ?>)
  </span>
</div>

<!-- ======================================================
     PART A – ANNEX 1: DATA SUBMITTED BY REPORTING ENTITY
     ====================================================== -->
<div class="sh">Part A – Annex 1: Data Submitted by Reporting Entity</div>

<!-- A1. CUSTOMER RISK -->
<div class="ssh">A1. Customer Risk – Transaction Data by Customer Type (Rs.)</div>
<p class="note">Amounts in Nepalese Rupees as submitted by the reporting entity for the fiscal year.</p>

<table>
  <thead>
    <tr>
      <th rowspan="3" style="width:26%;text-align:left;padding-left:8px">Significant Activities</th>
      <th colspan="4">Customer Category</th>
      <th rowspan="3">Total (Rs.)</th>
      <th rowspan="3">Risk Score</th>
    </tr>
    <tr>
      <th colspan="2" class="th2">Natural Person</th>
      <th colspan="2" class="th2">Legal Person</th>
    </tr>
    <tr>
      <th class="th3">NP Resident<br>(Rs.)</th>
      <th class="th3">NP Non-Resident<br>(Rs.)</th>
      <th class="th3">LP Resident<br>(Rs.)</th>
      <th class="th3">LP Non-Resident<br>(Rs.)</th>
    </tr>
    <tr>
      <th style="background:#555;color:#ff9;text-align:left;padding-left:8px">Risk Weight</th>
      <th style="background:#555;color:#ff9">1</th>
      <th style="background:#555;color:#ff9">3</th>
      <th style="background:#555;color:#ff9">4</th>
      <th style="background:#555;color:#ff9">5</th>
      <th style="background:#555;color:#fff" colspan="2"></th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($ACTIVITY_DEFS as $sec => $def):
    echo "<tr><td colspan='7' class='tr-sec'>{$def['label']}</td></tr>\n";
    foreach ($def['fields'] as $field => $lbl):
        $v1 = fv($cr, $sec, 1, $field);
        $v2 = fv($cr, $sec, 2, $field);
        $v3 = fv($cr, $sec, 3, $field);
        $v4 = fv($cr, $sec, 4, $field);
        $tot = $v1+$v2+$v3+$v4;
        $rs  = weightedRiskScore([$v1,$v2,$v3,$v4], $CRW);
        $isCount = ($field === 'DematAccountCount');
        $dp = $isCount ? 0 : 2;
?>
    <tr>
      <td class="tr-act"><?= htmlspecialchars($lbl) ?></td>
      <td><?= $v1>0?number_format($v1,$dp):'<span class="na">-</span>' ?></td>
      <td><?= $v2>0?number_format($v2,$dp):'<span class="na">-</span>' ?></td>
      <td><?= $v3>0?number_format($v3,$dp):'<span class="na">-</span>' ?></td>
      <td><?= $v4>0?number_format($v4,$dp):'<span class="na">-</span>' ?></td>
      <td class="tr-tot"><?= $tot>0?number_format($tot,$dp):'<span class="na">-</span>' ?></td>
      <td class="td-rs <?= $rs>0?catCls(riskCat($rs)):'' ?>"><?= $rs>0?number_format($rs,2):'<span class="na">-</span>' ?></td>
    </tr>
<?php   endforeach; endforeach; ?>
  </tbody>
</table>

<!-- A2. PEPS RISK -->
<div class="ssh">A2. PEPs Risk – Transaction Data by PEPs Category (Rs.)</div>

<table>
  <thead>
    <tr>
      <th rowspan="2" style="width:26%;text-align:left;padding-left:8px">Significant Activities</th>
      <th colspan="2">PEPs Category</th>
      <th rowspan="2">Total (Rs.)</th>
      <th rowspan="2">Risk Score</th>
    </tr>
    <tr>
      <th class="th2">Domestic PEPs (Rs.)</th>
      <th class="th2">Foreign PEPs (Rs.)</th>
    </tr>
    <tr>
      <th style="background:#555;color:#ff9;text-align:left;padding-left:8px">Risk Weight</th>
      <th style="background:#555;color:#ff9">4</th>
      <th style="background:#555;color:#ff9">5</th>
      <th style="background:#555;color:#fff" colspan="2"></th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($ACTIVITY_DEFS as $sec => $def):
    echo "<tr><td colspan='5' class='tr-sec'>{$def['label']}</td></tr>\n";
    foreach ($def['fields'] as $field => $lbl):
        $v6 = fv($pe, $sec, 6, $field);  // Domestic
        $v5 = fv($pe, $sec, 5, $field);  // Foreign
        $tot = $v6+$v5;
        $rs  = weightedRiskScore([$v6,$v5], $PEW);
        $dp  = ($field === 'DematAccountCount') ? 0 : 2;
?>
    <tr>
      <td class="tr-act"><?= htmlspecialchars($lbl) ?></td>
      <td><?= $v6>0?number_format($v6,$dp):'<span class="na">-</span>' ?></td>
      <td><?= $v5>0?number_format($v5,$dp):'<span class="na">-</span>' ?></td>
      <td class="tr-tot"><?= $tot>0?number_format($tot,$dp):'<span class="na">-</span>' ?></td>
      <td class="td-rs <?= $rs>0?catCls(riskCat($rs)):'' ?>"><?= $rs>0?number_format($rs,2):'<span class="na">-</span>' ?></td>
    </tr>
<?php   endforeach; endforeach; ?>
  </tbody>
</table>

<!-- A3. DELIVERY CHANNEL -->
<div class="ssh">A3. Delivery Channel – Transaction Data by Channel Type (Rs.)</div>

<table>
  <thead>
    <tr>
      <th rowspan="2" style="width:26%;text-align:left;padding-left:8px">Significant Activities</th>
      <th colspan="2">Delivery Channel</th>
      <th rowspan="2">Total (Rs.)</th>
      <th rowspan="2">Risk Score</th>
    </tr>
    <tr>
      <th class="th2">Over the Counter (Rs.)</th>
      <th class="th2">Non-Face-to-Face (Rs.)</th>
    </tr>
    <tr>
      <th style="background:#555;color:#ff9;text-align:left;padding-left:8px">Risk Weight</th>
      <th style="background:#555;color:#ff9">3</th>
      <th style="background:#555;color:#ff9">5</th>
      <th style="background:#555;color:#fff" colspan="2"></th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($ACTIVITY_DEFS as $sec => $def):
    echo "<tr><td colspan='5' class='tr-sec'>{$def['label']}</td></tr>\n";
    foreach ($def['fields'] as $field => $lbl):
        $v7 = fv($dc, $sec, 7, $field);  // OTC
        $v8 = fv($dc, $sec, 8, $field);  // Non-face
        $tot = $v7+$v8;
        $rs  = weightedRiskScore([$v7,$v8], $DCW);
        $dp  = ($field === 'DematAccountCount') ? 0 : 2;
?>
    <tr>
      <td class="tr-act"><?= htmlspecialchars($lbl) ?></td>
      <td><?= $v7>0?number_format($v7,$dp):'<span class="na">-</span>' ?></td>
      <td><?= $v8>0?number_format($v8,$dp):'<span class="na">-</span>' ?></td>
      <td class="tr-tot"><?= $tot>0?number_format($tot,$dp):'<span class="na">-</span>' ?></td>
      <td class="td-rs <?= $rs>0?catCls(riskCat($rs)):'' ?>"><?= $rs>0?number_format($rs,2):'<span class="na">-</span>' ?></td>
    </tr>
<?php   endforeach; endforeach; ?>
  </tbody>
</table>

<!-- A4. GEOGRAPHIC ZONE -->
<div class="ssh">A4. Geographic Zone – Transaction Data by Zone (Rs.)</div>

<table>
  <thead>
    <tr>
      <th rowspan="2" style="width:26%;text-align:left;padding-left:8px">Significant Activities</th>
      <th colspan="4">Geographic Zone</th>
      <th rowspan="2">Total (Rs.)</th>
      <th rowspan="2">Risk Score</th>
    </tr>
    <tr>
      <th class="th2">Rural Areas (Rs.)</th>
      <th class="th2">Other Urban (Rs.)</th>
      <th class="th2">Kathmandu (Rs.)</th>
      <th class="th2">Border Areas (Rs.)</th>
    </tr>
    <tr>
      <th style="background:#555;color:#ff9;text-align:left;padding-left:8px">Risk Weight</th>
      <th style="background:#555;color:#ff9">1</th>
      <th style="background:#555;color:#ff9">2</th>
      <th style="background:#555;color:#ff9">3</th>
      <th style="background:#555;color:#ff9">4</th>
      <th style="background:#555;color:#fff" colspan="2"></th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($ACTIVITY_DEFS as $sec => $def):
    echo "<tr><td colspan='7' class='tr-sec'>{$def['label']}</td></tr>\n";
    foreach ($def['fields'] as $field => $lbl):
        $v9  = fv($gz, $sec,  9, $field);
        $v10 = fv($gz, $sec, 10, $field);
        $v11 = fv($gz, $sec, 11, $field);
        $v12 = fv($gz, $sec, 12, $field);
        $tot = $v9+$v10+$v11+$v12;
        $rs  = weightedRiskScore([$v9,$v10,$v11,$v12], $GZW);
        $dp  = ($field === 'DematAccountCount') ? 0 : 2;
?>
    <tr>
      <td class="tr-act"><?= htmlspecialchars($lbl) ?></td>
      <td><?= $v9 >0?number_format($v9, $dp):'<span class="na">-</span>' ?></td>
      <td><?= $v10>0?number_format($v10,$dp):'<span class="na">-</span>' ?></td>
      <td><?= $v11>0?number_format($v11,$dp):'<span class="na">-</span>' ?></td>
      <td><?= $v12>0?number_format($v12,$dp):'<span class="na">-</span>' ?></td>
      <td class="tr-tot"><?= $tot>0?number_format($tot,$dp):'<span class="na">-</span>' ?></td>
      <td class="td-rs <?= $rs>0?catCls(riskCat($rs)):'' ?>"><?= $rs>0?number_format($rs,2):'<span class="na">-</span>' ?></td>
    </tr>
<?php   endforeach; endforeach; ?>
  </tbody>
</table>

<!-- A5. TOTAL ASSETS -->
<div class="ssh">A5. Total Assets Declared</div>
<div class="kv">
  <div class="kvr">
    <div class="kvk">Total Assets (Rs.) – as declared for the fiscal year</div>
    <div class="kvv"><?= $ta !== null ? number_format($ta, 2) : '<span class="na">Not submitted</span>' ?></div>
  </div>
</div>
<p class="note">
  Total Assets rating for structural risk is computed by linear interpolation across all Reporting Entities:
  highest assets = 1.00 (Very Low risk), lowest = 5.00 (Very High risk).
</p>

<!-- ======================================================
     PART B – INHERENT RISK CALCULATION
     ====================================================== -->
<div class="sh">Part B – Inherent Risk Calculation (Quantitative Component – 60%)</div>
<p class="note">
  Inherent risk per activity = simple average of non-zero dimension scores.
  Each dimension score is the transaction-amount-weighted average of risk weights for that category.
</p>

<table>
  <thead>
    <tr>
      <th rowspan="2" style="width:24%;text-align:left;padding-left:8px">Activity</th>
      <th rowspan="2" style="width:7%">SEBON<br>Weight</th>
      <th colspan="4">Risk Dimension Scores</th>
      <th rowspan="2" style="width:10%">Total Inherent<br>Risk</th>
      <th rowspan="2" style="width:9%">Category</th>
    </tr>
    <tr>
      <th class="th2" style="width:9%">Customer<br>Risk</th>
      <th class="th2" style="width:9%">PEPs<br>Risk</th>
      <th class="th2" style="width:9%">Delivery<br>Channel</th>
      <th class="th2" style="width:9%">Geographic<br>Zone</th>
    </tr>
  </thead>
  <tbody>
<?php
$prevSec = '';
foreach ($acts as $act):
    if ($act['sec_label'] !== $prevSec) {
        $prevSec = $act['sec_label'];
        echo "<tr><td colspan='8' class='tr-sec'>{$act['sec_label']}</td></tr>\n";
    }
    $ir  = $act['inherent_risk'];
    $cat = riskCat($ir);
    $cls = $ir > 0 ? catCls($cat) : 'c-na';
?>
  <tr>
    <td class="tr-act"><?= htmlspecialchars($act['label']) ?></td>
    <td class="td-wt"><?= number_format($act['sebon_wt']*100,0) ?>%</td>
    <td><?= $act['cr']>0?number_format($act['cr'],2):'<span class="na">-</span>' ?></td>
    <td><?= $act['pr']>0?number_format($act['pr'],2):'<span class="na">-</span>' ?></td>
    <td><?= $act['dr']>0?number_format($act['dr'],2):'<span class="na">-</span>' ?></td>
    <td><?= $act['gr']>0?number_format($act['gr'],2):'<span class="na">-</span>' ?></td>
    <td class="td-rs <?= $cls ?>"><?= $ir>0?number_format($ir,2):'<span class="na">N/A</span>' ?></td>
    <td class="<?= $cls ?>"><?= $ir>0?$cat:'<span class="c-na">N/A</span>' ?></td>
  </tr>
<?php endforeach; ?>
  <tr class="tr-tot">
    <td colspan="6" style="text-align:right;padding-right:10px">
      Overall Inherent Risk (weighted avg. of activities with data):
    </td>
    <td class="td-rs"><?= number_format($inh,2) ?></td>
    <td class="<?= catCls(riskCat($inh)) ?>"><?= riskCat($inh) ?></td>
  </tr>
  </tbody>
</table>

<!-- ======================================================
     PART C – ANNEX 2: QUESTIONNAIRE & SEBON GRADING
     ====================================================== -->
<div class="sh">Part C – Annex 2: Questionnaire Submitted by Entity and Graded by SEBON</div>
<p class="note">
  All responses submitted by the reporting entity are shown below with SEBON's remarks,
  rating (1=Very Low risk / strong controls, 5=Very High risk / deficient controls),
  and the identity of the SEBON reviewer who assigned each rating.
</p>

<?php if (empty($ann2)): ?>
<p style="color:#888;font-style:italic;margin:8px 0 14px">
  No questionnaire responses found for this participant and fiscal year.
</p>
<?php else:
    $qNum = 1;
    foreach ($ann2 as $secId => $sec):
        $rasCol  = isset($ANNEX2_SECTION_MAP[$secId]) ? $ANNEX2_SECTION_MAP[$secId] : null;
        $rasScore = ($rasCol && $qual && isset($qual[$rasCol])) ? (float)$qual[$rasCol] : null;
        $rasLabel = '';
        if ($rasCol && isset($RAS_COMPONENTS[$rasCol]))
            $rasLabel = $RAS_COMPONENTS[$rasCol][0] . ' (' . $RAS_COMPONENTS[$rasCol][1] . '%)';
?>
  <div class="a2-sh">
    <?= htmlspecialchars($sec['name']) ?>
    <?php if ($rasLabel): ?> &nbsp;–&nbsp; RAS Component: <?= htmlspecialchars($rasLabel) ?><?php endif; ?>
    <?php if ($rasScore !== null): ?>
      &nbsp;|&nbsp; Section Score: <strong><?= number_format($rasScore,2) ?></strong>
      (<?= riskCat($rasScore) ?>)
    <?php endif; ?>
  </div>

  <table class="a2t">
    <thead>
      <tr>
        <th class="c-sn">#</th>
        <th class="c-q">Question</th>
        <th class="c-dr">Directive<br>Ref.</th>
        <th class="c-st">Status</th>
        <th class="c-mr">Entity's Remarks</th>
        <th class="c-sr">SEBON Remarks</th>
        <th class="c-rt">SEBON<br>Rating</th>
        <th class="c-rv">Reviewed By<br>(SEBON User)</th>
      </tr>
    </thead>
    <tbody>
<?php
        $secRatings = [];
        $reviewers  = [];
        foreach ($sec['rows'] as $row):
            $status   = $row['Annex2_F3Ques_Ans_collection_status'] ? 'Yes' : 'No';
            $mpRmk    = trim((string)($row['Annex2_F3Ques_Ans_collection_RemarksByMP'] ?? ''));
            $sebRmk   = trim((string)($row['Annex2_F3Ques_Ans_collection_RemarksBySebon'] ?? ''));
            $sebRat   = $row['Annex2_F3Ques_Ans_collection_RatingBySebon'];
            $reviewer = trim((string)($row['reviewer_username'] ?? ''));
            $stCls    = ($status === 'Yes') ? 'sy' : 'sn';
            if ($sebRat !== null && is_numeric($sebRat)) $secRatings[] = (float)$sebRat;
            if ($reviewer) $reviewers[$reviewer] = true;
            $rat = ($sebRat !== null && is_numeric($sebRat)) ? (float)$sebRat : null;
?>
      <tr>
        <td class="c-sn"><?= $qNum++ ?></td>
        <td class="c-q"><?= htmlspecialchars($row['Annex2_F3QuestionsList']) ?></td>
        <td class="c-dr"><?= htmlspecialchars((string)($row['Annex2_F3RelatedDirectives'] ?? '')) ?></td>
        <td class="c-st <?= $stCls ?>"><?= $status ?></td>
        <td class="c-mr"><?= nl2br(htmlspecialchars($mpRmk)) ?: '<span class="na">–</span>' ?></td>
        <td class="c-sr"><?= nl2br(htmlspecialchars($sebRmk)) ?: '<span class="na">–</span>' ?></td>
        <td class="c-rt">
          <?php if ($rat !== null):
            $rc  = riskCat($rat);
            $rcl = catCls($rc);
          ?>
          <span class="<?= $rcl ?>" style="display:inline-block;padding:1px 5px">
            <?= number_format($rat,2) ?>
          </span>
          <?php else: ?><span class="na">–</span><?php endif; ?>
        </td>
        <td class="c-rv"><?= $reviewer ? htmlspecialchars($reviewer) : '<span class="na">–</span>' ?></td>
      </tr>
<?php   endforeach; // rows
        $secAvg = !empty($secRatings)
            ? round(array_sum($secRatings)/count($secRatings), 2) : null;
        $reviewerList = implode(', ', array_keys($reviewers));
?>
      <tr class="a2-avg">
        <td colspan="5" style="text-align:right;padding-right:8px">
          Section average (<?= count($secRatings) ?> rated
          question<?= count($secRatings)!==1?'s':'' ?>)
          <?php if ($reviewerList): ?>
            &nbsp;|&nbsp; Graded by: <strong><?= htmlspecialchars($reviewerList) ?></strong>
          <?php endif; ?>:
        </td>
        <td colspan="3">
          <?php if ($secAvg !== null): ?>
            <strong><?= number_format($secAvg,2) ?></strong>
            &nbsp; <?= riskCat($secAvg) ?>
            <?php if ($rasScore !== null && abs($rasScore - $secAvg) > 0.01): ?>
              &nbsp;<span style="font-size:8pt;color:#777">
                (saved RAS score: <?= number_format($rasScore,2) ?>)
              </span>
            <?php endif; ?>
          <?php else: echo '<span class="na">Not graded</span>'; endif; ?>
        </td>
      </tr>
    </tbody>
  </table>
<?php endforeach; endif; // annex2 ?>

<!-- C2. RAS SCORE SUMMARY -->
<div class="ssh">C2. Risk Controls and Mitigants (RAS) Score Summary – Qualitative Component (40%)</div>

<table>
  <thead>
    <tr>
      <th style="text-align:left;padding-left:8px;width:36%">RAS Component</th>
      <th style="width:10%">Weight</th>
      <th style="width:12%">Score (1–5)</th>
      <th style="width:16%">Weighted Contribution</th>
      <th style="width:12%">Category</th>
    </tr>
  </thead>
  <tbody>
<?php
$rasWS = 0.0; $rasWT = 0;
foreach ($RAS_COMPONENTS as $col => list($label, $wt)):
    $v   = ($qual && isset($qual[$col])) ? (float)$qual[$col] : 0.0;
    $wc  = $v > 0 ? round($v * $wt / 100, 4) : 0.0;
    $cat = riskCat($v);
    $cls = $v > 0 ? catCls($cat) : 'c-na';
    if ($v > 0) { $rasWS += $v * $wt; $rasWT += $wt; }
?>
  <tr>
    <td style="text-align:left;padding-left:8px"><?= htmlspecialchars($label) ?></td>
    <td class="td-wt"><?= $wt ?>%</td>
    <td><?= $v>0?number_format($v,2):'<span class="na">0.00</span>' ?></td>
    <td><?= $v>0?number_format($wc,4):'<span class="na">–</span>' ?></td>
    <td class="<?= $cls ?>"><?= $cat ?></td>
  </tr>
<?php endforeach; ?>
  <tr class="tr-tot">
    <td colspan="3" style="text-align:right;padding-right:10px">Total RAS Score (weighted average):</td>
    <td colspan="2" class="<?= catCls(riskCat($ras)) ?>">
      <strong><?= number_format($ras,2) ?></strong> &nbsp; <?= riskCat($ras) ?>
    </td>
  </tr>
  </tbody>
</table>

<!-- ======================================================
     PART D – FINAL RISK CALCULATIONS
     ====================================================== -->
<div class="sh">Part D – Final Risk Calculations</div>

<!-- D1. COMPOSITE RISK -->
<div class="ssh">D1. Composite Risk by Activity – (Inherent Risk × 60%) + (RAS × 40%)</div>
<p class="note">Activities with no inherent risk data are excluded from the composite and overall calculation.</p>

<table>
  <thead>
    <tr>
      <th style="width:26%;text-align:left;padding-left:8px">Activity</th>
      <th style="width:8%">SEBON<br>Weight</th>
      <th style="width:11%">Inherent<br>Risk (60%)</th>
      <th style="width:10%">RAS<br>(40%)</th>
      <th style="width:14%">Composite<br>Risk</th>
      <th style="width:12%">Category</th>
    </tr>
  </thead>
  <tbody>
<?php
$prevSec = '';
foreach ($acts as $act):
    if ($act['sec_label'] !== $prevSec) {
        $prevSec = $act['sec_label'];
        echo "<tr><td colspan='6' class='tr-sec'>{$act['sec_label']}</td></tr>\n";
    }
    $ir  = $act['inherent_risk'];
    $cr2 = $act['composite_risk'];
    $cat = riskCat($cr2);
    $cls = $cr2 > 0 ? catCls($cat) : 'c-na';
?>
  <tr>
    <td class="tr-act"><?= htmlspecialchars($act['label']) ?></td>
    <td class="td-wt"><?= number_format($act['sebon_wt']*100,0) ?>%</td>
    <?php if ($ir > 0): ?>
    <td><?= number_format($ir,2) ?></td>
    <td><?= number_format($ras,2) ?></td>
    <td class="td-rs <?= $cls ?>"><?= number_format($cr2,2) ?></td>
    <td class="<?= $cls ?>"><?= $cat ?></td>
    <?php else: ?>
    <td class="na" colspan="4">No data submitted</td>
    <?php endif; ?>
  </tr>
<?php endforeach; ?>
  <tr class="tr-fin">
    <td colspan="4" style="text-align:right;padding-right:10px">
      Overall Composite Risk (weighted avg. of activities with data):
    </td>
    <td style="font-size:12pt"><?= number_format($cmp,2) ?></td>
    <td class="<?= catCls(riskCat($cmp)) ?>" style="color:#111"><?= riskCat($cmp) ?></td>
  </tr>
  </tbody>
</table>

<!-- D2. STRUCTURAL RISK -->
<div class="ssh">D2. Structural Risk Breakdown</div>
<div class="kv">
  <div class="kvr">
    <div class="kvk">Total Assets Rating (90% weight) – linear interpolation across all participants</div>
    <div class="kvv">
      <?php $tar = ($strRaw && isset($strRaw['TotalAssets'])) ? (float)$strRaw['TotalAssets'] : 0.0; ?>
      <?= number_format($tar,2) ?> &nbsp;(<?= riskCat($tar) ?>)
    </div>
  </div>
  <div class="kvr">
    <div class="kvk">Financial Group Rating (10% weight)</div>
    <div class="kvv">
      <?php $fgr = ($strRaw && isset($strRaw['FinancialGroup'])) ? (float)$strRaw['FinancialGroup'] : 0.0; ?>
      <?= number_format($fgr,2) ?> &nbsp;(<?= riskCat($fgr) ?>)
    </div>
  </div>
  <div class="kvr" style="background:#e8e8e8">
    <div class="kvk"><strong>Structural Risk = (0.90 × <?= number_format($tar,2) ?>) + (0.10 × <?= number_format($fgr,2) ?>)</strong></div>
    <div class="kvv" style="font-size:12pt"><strong><?= number_format($str,2) ?> &nbsp; <?= riskCat($str) ?></strong></div>
  </div>
</div>

<!-- D3. NET PROFILE RISK -->
<div class="ssh">D3. Net Profile Risk</div>
<div class="kv">
  <div class="kvr">
    <div class="kvk">Structural Risk (25% weight)</div>
    <div class="kvv"><?= number_format($str,2) ?> &nbsp;(<?= riskCat($str) ?>)</div>
  </div>
  <div class="kvr">
    <div class="kvk">Inherent Risk (75% weight)</div>
    <div class="kvv"><?= number_format($inh,2) ?> &nbsp;(<?= riskCat($inh) ?>)</div>
  </div>
  <div class="kvr" style="background:#e8e8e8">
    <div class="kvk">
      <strong>Net Profile Risk = (0.25 × <?= number_format($str,2) ?>) + (0.75 × <?= number_format($inh,2) ?>)</strong>
    </div>
    <div class="kvv" style="font-size:12pt">
      <strong><?= number_format($net,2) ?> &nbsp; <?= riskCat($net) ?></strong>
    </div>
  </div>
</div>
<p class="note">
  Net Profile Risk captures inherent exposure incorporating the structural characteristics of the entity.
  It is independent of qualitative controls and reflects risk before mitigation measures.
</p>

<!-- ======================================================
     APPENDIX: METHODOLOGY
     ====================================================== -->
<div class="sh">Appendix – Methodology Reference</div>
<div class="mbox">
  <strong>Formulas:</strong><br>
  &bull; Customer/PEPs/Delivery/Geo Risk Score = transaction-amount-weighted average of category risk weights<br>
  &bull; Inherent Risk (per activity) = simple average of non-zero dimension scores {Customer, PEPs, Delivery, Geographic}<br>
  &bull; Composite Risk (per activity) = (Inherent Risk &times; 0.60) + (RAS &times; 0.40) &nbsp;[only where Inherent Risk &gt; 0]<br>
  &bull; Overall Composite Risk = SEBON-weight-weighted average of non-zero activity composite risks<br>
  &bull; RAS Score = weighted avg. of 7 components [CG:30%, PP:10%, RM:20%, IC:15%, CF:15%, TR:5%, RR:5%]<br>
  &bull; Structural Risk = (Total Assets Rating &times; 0.90) + (Financial Group Rating &times; 0.10)<br>
  &bull; Net Profile Risk = (Structural Risk &times; 0.25) + (Inherent Risk &times; 0.75)<br><br>

  <strong>Risk Category Scale:</strong>
  <table>
    <thead><tr><th>Category</th><th>Score Range</th><th>Interpretation</th></tr></thead>
    <tbody>
      <tr><td class="c-vl">Very Low</td><td>1.00 – 1.50</td><td>Minimal risk exposure / very strong controls</td></tr>
      <tr><td class="c-lo">Low</td><td>1.51 – 2.50</td><td>Low risk exposure / adequate controls</td></tr>
      <tr><td class="c-me">Medium</td><td>2.51 – 3.50</td><td>Moderate risk / controls require improvement</td></tr>
      <tr><td class="c-hi">High</td><td>3.51 – 4.00</td><td>High risk / significant control deficiencies</td></tr>
      <tr><td class="c-vh">Very High</td><td>4.01 – 5.00</td><td>Very high risk / critical control failures</td></tr>
    </tbody>
  </table><br>

  <strong>Annex 1 Risk Weights by Dimension:</strong><br>
  Customer Risk: NP-Resident=1, NP-Non-Resident=3, LP-Resident=4, LP-Non-Resident=5<br>
  PEPs Risk: Domestic PEPs=4, Foreign PEPs=5<br>
  Delivery Channel: Over the Counter=3, Non-Face-to-Face=5<br>
  Geographic Zone: Rural=1, Other Urban=2, Kathmandu=3, Border Areas=4
</div>

<!-- REPORT FOOTER -->
<div class="rf">
  
  <span> &nbsp;&nbsp; AML/CFT Risk Matrix System – SEBON</span>
</div>

</body>
</html>
<?php
    return ob_get_clean();
}

// =========================================================
// COMPUTE ACTIVITY RISKS FOR ONE PARTICIPANT
// =========================================================

function computeActivities($allData, $ras) {
    global $ACTIVITY_DEFS, $SEBON_WT_MAP;

    $CRW = [1, 3, 4, 5];
    $PEW = [4, 5];
    $DCW = [3, 5];
    $GZW = [1, 2, 3, 4];

    $acts = [];
    $inhSum = 0.0; $inhWt = 0.0;
    $cmpSum = 0.0; $cmpWt = 0.0;

    foreach ($ACTIVITY_DEFS as $sec => $def) {
        $fieldKeys = array_keys($def['fields']);
        foreach ($fieldKeys as $field) {
            $cr = weightedRiskScore([
                fv($allData['customer_risk'],    $sec, 1, $field),
                fv($allData['customer_risk'],    $sec, 2, $field),
                fv($allData['customer_risk'],    $sec, 3, $field),
                fv($allData['customer_risk'],    $sec, 4, $field),
            ], $CRW);
            $pr = weightedRiskScore([
                fv($allData['peps_risk'],        $sec, 6, $field),
                fv($allData['peps_risk'],        $sec, 5, $field),
            ], $PEW);
            $dr = weightedRiskScore([
                fv($allData['delivery_channel'], $sec, 7, $field),
                fv($allData['delivery_channel'], $sec, 8, $field),
            ], $DCW);
            $gr = weightedRiskScore([
                fv($allData['geographic_zone'],  $sec,  9, $field),
                fv($allData['geographic_zone'],  $sec, 10, $field),
                fv($allData['geographic_zone'],  $sec, 11, $field),
                fv($allData['geographic_zone'],  $sec, 12, $field),
            ], $GZW);

            $ir  = avgNonZero([$cr, $pr, $dr, $gr]);
            $cmp = $ir > 0 ? round($ir * 0.60 + $ras * 0.40, 4) : 0.0;
            $swt = isset($SEBON_WT_MAP[$sec][$field]) ? $SEBON_WT_MAP[$sec][$field] / 100 : 0.0;

            if ($ir > 0)  { $inhSum += $ir  * $swt; $inhWt += $swt; }
            if ($cmp > 0) { $cmpSum += $cmp * $swt; $cmpWt += $swt; }

            $acts[] = [
                'sec_label'     => $def['label'],
                'label'         => $def['fields'][$field],
                'sebon_wt'      => $swt,
                'cr'            => $cr,
                'pr'            => $pr,
                'dr'            => $dr,
                'gr'            => $gr,
                'inherent_risk' => $ir,
                'composite_risk'=> $cmp,
            ];
        }
    }

    $overallInherent  = $inhWt > 0 ? round($inhSum / $inhWt, 4) : 0.0;
    $overallComposite = $cmpWt > 0 ? round($cmpSum / $cmpWt, 4) : 0.0;

    return [$acts, $overallInherent, $overallComposite];
}

// =========================================================
// SELECTION FORM
// =========================================================

$selectedFY = isset($_POST['fiscal_year']) ? (int)$_POST['fiscal_year'] : 0;

if (!$selectedFY) {
    $stmtFY = sqlsrv_query($conn, "SELECT FiscalYear_id,FiscalYearName FROM FiscalYear ORDER BY FiscalYear_id DESC");
    $fys = [];
    if ($stmtFY) while ($r = sqlsrv_fetch_array($stmtFY, SQLSRV_FETCH_ASSOC)) $fys[] = $r;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Generate Risk Reports</title>
<style>
body{font-family:Arial,sans-serif;padding:50px;background:#f2f2f2}
.box{max-width:540px;margin:0 auto;background:#fff;padding:36px 42px;border:1px solid #ccc}
h1{font-size:16pt;margin-bottom:5px}p{color:#555;font-size:10pt;margin-bottom:20px;line-height:1.7}
label{font-weight:bold;font-size:10.5pt;display:block;margin-bottom:7px}
select{width:100%;padding:9px 11px;font-size:11pt;border:1px solid #aaa}
button{margin-top:18px;width:100%;padding:12px;background:#222;color:#fff;font-size:11pt;
       font-weight:bold;border:none;cursor:pointer}
button:hover{background:#444}
.note{margin-top:20px;padding-top:14px;border-top:1px solid #ddd;font-size:8.5pt;color:#888;line-height:1.8}
</style></head><body>
<div class="box">
  <h1>AML/CFT Risk Assessment Report Generator</h1>
  <p>
    Generates one report per eligible Reporting Entity, packaged as a ZIP archive.
    Each report contains: Annex 1 raw data (all four risk dimensions), Annex 2 questionnaire
    with SEBON grading and reviewer identity, and all derived risk calculations.
  </p>
  <form method="POST">
    <label for="fy">Select Fiscal Year</label>
    <select name="fiscal_year" id="fy" required>
      <option value="">-- Select --</option>
      <?php foreach ($fys as $fy): ?>
        <option value="<?= $fy['FiscalYear_id'] ?>"><?= htmlspecialchars($fy['FiscalYearName']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Generate and Download ZIP</button>
  </form>
  <div class="note">
    Only participants with a completed composite risk score are included (requires both Annex 1
    quantitative data and Annex 2 SEBON-graded qualitative data to be non-zero).<br>
    Open each HTML file in a browser – Ctrl+P to save as PDF.
  </div>
</div>
</body></html>
<?php
    exit;
}

// =========================================================
// MAIN GENERATION LOOP
// =========================================================

$stmtFY = sqlsrv_query($conn, "SELECT FiscalYearName FROM FiscalYear WHERE FiscalYear_id=?", [$selectedFY]);
$fyRow  = sqlsrv_fetch_array($stmtFY, SQLSRV_FETCH_ASSOC);
$fyName = $fyRow ? $fyRow['FiscalYearName'] : (string)$selectedFY;
$genDate = date('Y-m-d');

$tempDir = sys_get_temp_dir() . '/aml_rpt_' . $selectedFY . '_' . time();
mkdir($tempDir, 0777, true);
$reportCount = 0;

$stmtMP = sqlsrv_query($conn,
    "SELECT MarketParticipant_id,MarketParticipantName FROM MarketParticipant
     WHERE Status=1 ORDER BY MarketParticipantName");

while ($mp = sqlsrv_fetch_array($stmtMP, SQLSRV_FETCH_ASSOC)) {
    $mpId   = (int)$mp['MarketParticipant_id'];
    $mpName = $mp['MarketParticipantName'];

    // All quantitative data (four dimensions)
    $allData = [
        'customer_risk'    => fetchQuant($conn, $mpId, $selectedFY, 'customer_risk'),
        'peps_risk'        => fetchQuant($conn, $mpId, $selectedFY, 'peps_risk'),
        'delivery_channel' => fetchQuant($conn, $mpId, $selectedFY, 'delivery_channel'),
        'geographic_zone'  => fetchQuant($conn, $mpId, $selectedFY, 'geographic_zone'),
    ];

    // Qualitative – must exist and be non-zero
    $qualData = fetchQuali($conn, $mpId, $selectedFY);
    if (!$qualData) continue;

    $ras = totalRAS(
        $qualData['CorporateGovernance']    ?? 0,
        $qualData['PoliciesProcedures']     ?? 0,
        $qualData['RiskManagement']         ?? 0,
        $qualData['InternalControls']       ?? 0,
        $qualData['ComplianceFunction']     ?? 0,
        $qualData['Training']               ?? 0,
        $qualData['ReportingRecordKeeping'] ?? 0
    );
    if ($ras <= 0) continue;

    // Activity risks and composite
    list($activities, $overallInherent, $overallComposite) = computeActivities($allData, $ras);

    // Gate: composite must be > 0 (same as COMPOSITE_RISK_REPORT.php)
    if ($overallComposite <= 0) continue;

    // Structural and net profile risk
    $strRaw  = fetchStructural($conn, $mpId, $selectedFY);
    $strRisk = structuralRisk($strRaw);
    $netRisk = netProfileRisk($strRisk, $overallInherent);

    // Total assets declared by entity
    $totalAssets = fetchTotalAssets($conn, $mpId, $selectedFY);

    // Annex 2 questionnaire with SEBON grading
    $annex2 = fetchAnnex2($conn, $mpId, $selectedFY);

    $reportData = [
        'mp_id'          => $mpId,
        'mp_name'        => $mpName,
        'customer_risk'  => $allData['customer_risk'],
        'peps_risk'      => $allData['peps_risk'],
        'delivery_channel'=> $allData['delivery_channel'],
        'geographic_zone'=> $allData['geographic_zone'],
        'qualitative'    => $qualData,
        'structural_raw' => $strRaw ?? [],
        'structural_risk'=> $strRisk,
        'inherent_risk'  => $overallInherent,
        'composite_risk' => $overallComposite,
        'net_profile_risk'=> $netRisk,
        'ras'            => $ras,
        'total_assets'   => $totalAssets,
        'activities'     => $activities,
        'annex2'         => $annex2,
    ];

    $html  = buildReport($reportData, $fyName, $genDate);
    $safe  = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $mpName), '_');
    $fname = $safe . '_RiskReport_FY' . preg_replace('/[^a-zA-Z0-9]/', '', $fyName) . '.html';
    file_put_contents($tempDir . '/' . $fname, $html);
    $reportCount++;
}

// =========================================================
// EMPTY RESULT
// =========================================================

if ($reportCount === 0) {
    array_map('unlink', glob("$tempDir/*"));
    rmdir($tempDir);
?>
<!DOCTYPE html><html><head><title>No Reports</title>
<style>body{font-family:Arial;padding:50px;background:#f2f2f2}
.box{max-width:500px;margin:0 auto;background:#fff;padding:32px 36px;border:1px solid #ccc}
a{display:inline-block;margin-top:16px;padding:9px 20px;background:#222;color:#fff;
  text-decoration:none;font-weight:bold}</style></head><body>
<div class="box">
  <h2>No Eligible Reports Generated</h2>
  <p>
    No Reporting Entities have a completed composite risk score for
    <strong><?= htmlspecialchars($fyName) ?></strong>.<br><br>
    Eligibility requires: Annex 1 quantitative data submitted AND
    Annex 2 questionnaire graded by SEBON (scores saved to RiskControlsAndMitigants).
  </p>
  <a href="?">Go Back</a>
</div>
</body></html>
<?php
    exit;
}

// =========================================================
// PACKAGE AND DOWNLOAD ZIP
// =========================================================

$zipName = 'AML_CFT_Reports_FY' . preg_replace('/[^a-zA-Z0-9]/', '', $fyName)
           . '_' . date('Ymd_His') . '.zip';
$zipPath = sys_get_temp_dir() . '/' . $zipName;

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    foreach (glob("$tempDir/*.html") as $f) $zip->addFile($f, basename($f));
    $manifest  = "AML/CFT Risk Assessment Reports\n";
    $manifest .= "Fiscal Year  : $fyName\n";
    $manifest .= "Generated    : $genDate\n";
    $manifest .= "Total Reports: $reportCount\n";
    $manifest .= "---\n";
    $manifest .= "Eligibility  : Participants with composite risk > 0\n";
    $manifest .= "               (requires both Annex 1 data and SEBON-graded Annex 2)\n";
    $manifest .= "Usage        : Open each HTML in browser, Ctrl+P to save as PDF\n";
    $zip->addFromString('README.txt', $manifest);
    $zip->close();
} else {
    // Pure-PHP ZIP fallback (no extension needed)
    $zipData = ''; $centralDir = ''; $offset = 0; $fc = 0;
    foreach (glob("$tempDir/*.html") as $fp) {
        $fname   = basename($fp);
        $content = file_get_contents($fp);
        $ucSize  = strlen($content);
        $cData   = gzdeflate($content, 6);
        $cSize   = strlen($cData);
        $crc     = crc32($content);
        $local   = "\x50\x4b\x03\x04\x14\x00\x00\x00\x08\x00"
                 . pack('V', 0) . pack('V', $crc)
                 . pack('V', $cSize) . pack('V', $ucSize)
                 . pack('v', strlen($fname)) . pack('v', 0)
                 . $fname . $cData;
        $zipData .= $local;
        $centralDir .= "\x50\x4b\x01\x02\x14\x00\x14\x00\x00\x00\x08\x00"
                     . pack('V', 0) . pack('V', $crc)
                     . pack('V', $cSize) . pack('V', $ucSize)
                     . pack('v', strlen($fname)) . pack('v', 0) . pack('v', 0)
                     . pack('v', 0) . pack('v', 0) . pack('V', 32)
                     . pack('V', $offset) . $fname;
        $offset += strlen($local);
        $fc++;
    }
    $eocd = "\x50\x4b\x05\x06" . pack('v', 0) . pack('v', 0)
           . pack('v', $fc) . pack('v', $fc)
           . pack('V', strlen($centralDir))
           . pack('V', $offset) . pack('v', 0);
    file_put_contents($zipPath, $zipData . $centralDir . $eocd);
}

array_map('unlink', glob("$tempDir/*"));
rmdir($tempDir);

ob_end_clean();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');
readfile($zipPath);
unlink($zipPath);
exit;