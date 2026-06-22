<?php
if (!defined('ABSPATH')) exit;

$shops    = get_option('ci_shops', []);
$networks = get_option('ci_networks', []);
$active_networks = array_filter($networks, fn($n) => $n['active']);

if (!is_array($shops)) $shops = [];

/* ==== NORMALIZA ==== */
$changed = false;
foreach ($shops as $k => $s) {
    if (!isset($s['id']))           { $shops[$k]['id']           = time() + $k; $changed = true; }
    if (!isset($s['network_id']))   { $shops[$k]['network_id']   = 1;           $changed = true; }
    if (!isset($s['mid']))          { $shops[$k]['mid']          = '';          $changed = true; }
    if (!isset($s['publisher_id'])) { $shops[$k]['publisher_id'] = '';          $changed = true; }
    if (!isset($s['active']))       { $shops[$k]['active']       = 1;           $changed = true; }
    if (!isset($s['aliases']) || !is_array($s['aliases'])) { $shops[$k]['aliases'] = []; $changed = true; }
    if (!isset($s['link_template'])) { $shops[$k]['link_template'] = ''; $changed = true; }
}
if ($changed) update_option('ci_shops', $shops);

/* ==== DELETE ==== */
if (isset($_GET['delete'])) {
    check_admin_referer('ci_delete_shop');
    $del = intval($_GET['delete']);
    $shops = array_values(array_filter($shops, fn($s) => $s['id'] != $del));
    update_option('ci_shops', $shops);
    echo '<div class="updated"><p>Loja removida.</p></div>';
    $shops = get_option('ci_shops', []);
}

/* ==== SAVE ==== */
if (isset($_POST['ci_save_shop'])) {
    check_admin_referer('ci_save_shop');

    $id           = intval($_POST['id']);
    $name         = sanitize_text_field($_POST['name']);
    $network_id   = intval($_POST['network_id']);
    $mid          = sanitize_text_field($_POST['mid']);
    $publisher_id = sanitize_text_field($_POST['publisher_id']);
    $active       = isset($_POST['active']) ? 1 : 0;
    $aliases      = array_filter(array_map('trim', explode(',', $_POST['aliases'] ?? '')));
    $link_template = sanitize_text_field($_POST['link_template'] ?? '');

    if (!$name) {
        echo '<div class="error"><p>Nome é obrigatório.</p></div>';
    } else {
        $data = compact('name','network_id','mid','publisher_id','active','aliases','link_template');
        if ($id > 0) {
            foreach ($shops as $k => $s) {
                if ($s['id'] == $id) { $shops[$k] = array_merge(['id' => $id], $data); break; }
            }
        } else {
            $shops[] = array_merge(['id' => time()], $data);
        }
        update_option('ci_shops', array_values($shops));
        echo '<div class="updated"><p>Loja salva com sucesso.</p></div>';
        $shops = get_option('ci_shops', []);
    }
}

/* ==== EDIT ==== */
$edit = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    foreach ($shops as $s) { if ($s['id'] == $eid) { $edit = $s; break; } }
}

/* ==== SORT ==== */
$sort  = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'name';
$order = (isset($_GET['order']) && $_GET['order'] === 'desc') ? 'desc' : 'asc';
usort($shops, function($a, $b) use ($sort, $order) {
    $cmp = strcmp(strtolower($a[$sort] ?? ''), strtolower($b[$sort] ?? ''));
    return $order === 'desc' ? -$cmp : $cmp;
});

function ci_sort_url($col, $cs, $co) {
    return add_query_arg(['sort' => $col, 'order' => ($cs === $col && $co === 'asc') ? 'desc' : 'asc']);
}
function ci_sort_arrow($col, $cs, $co) {
    if ($cs !== $col) return ' <span style="color:#aaa">↕</span>';
    return $co === 'asc' ? ' ↑' : ' ↓';
}

// Mapeia network_id → nome para exibição
$network_map = [];
foreach ($networks as $n) $network_map[$n['id']] = $n['name'];
?>
<div class="wrap">
<h1>Lojas</h1>

<div class="card" style="max-width:820px;padding:20px;">
<h2 style="margin-top:0;"><?php echo $edit ? 'Editar Loja' : 'Nova Loja'; ?></h2>

<form method="post">
<?php wp_nonce_field('ci_save_shop'); ?>
<input type="hidden" name="id" value="<?php echo esc_attr($edit['id'] ?? ''); ?>">

<table class="form-table">

<tr>
    <th><label for="name">Nome *</label></th>
    <td>
        <input type="text" id="name" name="name" required class="regular-text" value="<?php echo esc_attr($edit['name'] ?? ''); ?>">
        <p class="description">Deve ser idêntico ao nome do anunciante na planilha (ou configure aliases).</p>
    </td>
</tr>

<tr>
    <th><label for="network_id">Rede *</label></th>
    <td>
        <select id="network_id" name="network_id">
            <?php foreach ($active_networks as $net): ?>
            <option value="<?php echo intval($net['id']); ?>" <?php selected($edit['network_id'] ?? 1, $net['id']); ?>>
                <?php echo esc_html($net['name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <a href="?page=coupon-importer-networks" target="_blank">Gerenciar Redes →</a>
        </p>
    </td>
</tr>

<tr>
    <th><label for="mid">MID (ID da Loja na Rede)</label></th>
    <td>
        <input type="text" id="mid" name="mid" class="regular-text" value="<?php echo esc_attr($edit['mid'] ?? ''); ?>">
        <p class="description">Usado como <code>{MID}</code> no template da rede. Ex AWIN: <code>47364</code>. Ex Rakuten: <code>47732</code>.</p>
    </td>
</tr>

<tr>
    <th><label for="publisher_id">Publisher ID (opcional)</label></th>
    <td>
        <input type="text" id="publisher_id" name="publisher_id" class="regular-text" value="<?php echo esc_attr($edit['publisher_id'] ?? ''); ?>">
        <p class="description">Sobrescreve o Publisher ID da rede para esta loja específica. Deixe vazio para usar o da rede.</p>
    </td>
</tr>

<tr>
    <th><label for="aliases">Aliases (vírgula)</label></th>
    <td>
        <input type="text" id="aliases" name="aliases" class="large-text" value="<?php echo isset($edit['aliases']) ? esc_attr(implode(',', $edit['aliases'])) : ''; ?>">
        <p class="description">Nomes alternativos que a loja pode ter na planilha.</p>
    </td>
</tr>

<tr>
    <th><label for="link_template">Template de Link (por loja)</label></th>
    <td>
        <input type="text" id="link_template" name="link_template" class="large-text" value="<?php echo esc_attr($edit['link_template'] ?? ''); ?>">
        <p class="description">
            <strong>Opcional.</strong> Se preenchido, substitui completamente o template da rede para esta loja.<br>
            Use <code>{URL}</code> para inserir o link do produto (já encoded). Deixe vazio para usar o template da rede normalmente.<br>
            Ex Admitad (cloaked link de afiliado): <code>https://example-network.com/g/your-tracking-id/?ulp={URL}</code><br>
            Ex link fixo sem parâmetro de produto: cole o link completo sem <code>{URL}</code>.
        </p>
    </td>
</tr>

<tr>
    <th>Ativa</th>
    <td><input type="checkbox" name="active" <?php checked($edit['active'] ?? 1, 1); ?>></td>
</tr>

</table>

<p>
    <button class="button button-primary" name="ci_save_shop">Salvar Loja</button>
    <?php if ($edit): ?>
    &nbsp; <a href="?page=coupon-importer-shops" class="button">Cancelar</a>
    <?php endif; ?>
</p>
</form>
</div>

<hr>
<h2>
    Lojas Cadastradas
    <span style="font-size:13px;font-weight:normal;color:#888;margin-left:8px;"><?php echo count($shops); ?> lojas</span>
</h2>

<table class="widefat striped">
<thead><tr>
    <th><a href="<?php echo esc_url(ci_sort_url('name', $sort, $order)); ?>">Nome<?php echo ci_sort_arrow('name', $sort, $order); ?></a></th>
    <th>Rede</th>
    <th>MID</th>
    <th>Publisher ID</th>
    <th>Template próprio</th>
    <th>Status</th>
    <th>Cupons</th>
    <th>Ações</th>
</tr></thead>
<tbody>
<?php foreach ($shops as $shop): ?>
<tr>
    <td>
        <strong><?php echo esc_html($shop['name']); ?></strong>
        <?php if (!empty($shop['aliases'])): ?>
        <br><small style="color:#888;">aliases: <?php echo esc_html(implode(', ', $shop['aliases'])); ?></small>
        <?php endif; ?>
    </td>
    <td><?php echo esc_html($network_map[$shop['network_id']] ?? '—'); ?></td>
    <td><?php echo esc_html($shop['mid'] ?: '—'); ?></td>
    <td><?php echo $shop['publisher_id'] ? esc_html($shop['publisher_id']) : '<span style="color:#888">usa rede</span>'; ?></td>
    <td style="font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($shop['link_template'] ?? ''); ?>">
        <?php echo !empty($shop['link_template']) ? esc_html($shop['link_template']) : '<span style="color:#aaa">—</span>'; ?>
    </td>
    <td><?php echo $shop['active'] ? 'Ativa' : '<span style="color:#888;">Inativa</span>'; ?></td>
    <td><?php echo ci_count_coupons($shop['name']); ?></td>
    <td>
        <a href="?page=coupon-importer-shops&edit=<?php echo intval($shop['id']); ?>">Editar</a> |
        <a href="<?php echo wp_nonce_url('?page=coupon-importer-shops&delete=' . intval($shop['id']), 'ci_delete_shop'); ?>"
           onclick="return confirm('Remover esta loja?')">Excluir</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
