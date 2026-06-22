<?php
if (!defined('ABSPATH')) exit;

$networks = get_option('ci_networks', []);
if (!$networks) { update_option('ci_networks', ci_default_networks()); $networks = ci_default_networks(); }

/* ==== DELETE ==== */
if (isset($_GET['delete'])) {
    check_admin_referer('ci_delete_network');
    $del = intval($_GET['delete']);
    $networks = array_values(array_filter($networks, fn($n) => $n['id'] != $del));
    update_option('ci_networks', $networks);
    echo '<div class="updated"><p>Rede removida.</p></div>';
}

/* ==== SAVE ==== */
if (isset($_POST['ci_save_network'])) {
    check_admin_referer('ci_save_network');

    $id           = intval($_POST['id']);
    $name         = sanitize_text_field($_POST['name']);
    $publisher_id = sanitize_text_field($_POST['publisher_id']);
    $template     = sanitize_text_field($_POST['template']);
    $link_direct  = isset($_POST['link_direct']) ? 1 : 0;
    $active       = isset($_POST['active']) ? 1 : 0;
    $notes        = sanitize_textarea_field($_POST['notes']);

    if (!$name) {
        echo '<div class="error"><p>Nome é obrigatório.</p></div>';
    } else {
        $data = compact('name','publisher_id','template','link_direct','active','notes');
        if ($id > 0) {
            foreach ($networks as $k => $n) {
                if ($n['id'] == $id) { $networks[$k] = array_merge(['id' => $id], $data); break; }
            }
        } else {
            $max_id = max(array_column($networks, 'id') ?: [0]);
            $networks[] = array_merge(['id' => $max_id + 1], $data);
        }
        update_option('ci_networks', array_values($networks));
        echo '<div class="updated"><p>Rede salva com sucesso.</p></div>';
        $networks = get_option('ci_networks', []);
    }
}

/* ==== EDIT ==== */
$edit = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    foreach ($networks as $n) { if ($n['id'] == $eid) { $edit = $n; break; } }
}
?>
<div class="wrap">
<h1>Redes de Afiliado</h1>

<div class="card" style="max-width:820px;padding:20px;">
<h2 style="margin-top:0;"><?php echo $edit ? 'Editar Rede' : 'Nova Rede'; ?></h2>

<form method="post">
<?php wp_nonce_field('ci_save_network'); ?>
<input type="hidden" name="id" value="<?php echo esc_attr($edit['id'] ?? ''); ?>">

<table class="form-table">

<tr>
    <th><label for="name">Nome *</label></th>
    <td><input type="text" id="name" name="name" required class="regular-text" value="<?php echo esc_attr($edit['name'] ?? ''); ?>"></td>
</tr>

<tr>
    <th><label for="publisher_id">Publisher ID / Amazon Tag</label></th>
    <td>
        <input type="text" id="publisher_id" name="publisher_id" class="regular-text" value="<?php echo esc_attr($edit['publisher_id'] ?? ''); ?>">
        <p class="description">
            Seu ID de publisher nessa rede. Usado como <code>{AFFID}</code> no template (pode ser sobrescrito por loja).<br>
            <strong>Para a rede Amazon:</strong> informe aqui o <em>Amazon Tag</em> (ex: <code>yourtag-20</code>). Ele será adicionado como <code>?tag=yourtag-20</code> ao final de cada link.
        </p>
    </td>
</tr>

<tr>
    <th><label for="template">Template do Link</label></th>
    <td>
        <input type="text" id="template" name="template" class="large-text" value="<?php echo esc_attr($edit['template'] ?? ''); ?>" <?php echo !empty($edit['link_direct']) ? 'disabled' : ''; ?>>
        <p class="description">
            Variáveis disponíveis: <code>{MID}</code> (ID da loja na rede), <code>{AFFID}</code> (seu publisher ID), <code>{URL}</code> (URL do produto — já encoded).<br>
            Ex AWIN: <code>https://www.awin1.com/cread.php?awinmid={MID}&awinaffid={AFFID}&platform=dl&ued={URL}</code><br>
            Ex Rakuten: <code>https://click.linksynergy.com/deeplink?id={AFFID}&mid={MID}&murl={URL}</code><br>
            <strong>Para a rede Amazon:</strong> deixe este campo em branco — o link é montado automaticamente adicionando <code>?tag=</code> ao link original.
        </p>
    </td>
</tr>

<tr>
    <th>Link Direto</th>
    <td>
        <label>
            <input type="checkbox" name="link_direct" id="link_direct" <?php checked($edit['link_direct'] ?? 0, 1); ?> onchange="document.getElementById('template').disabled=this.checked">
            O link vem pronto no CSV (não aplica template)
        </label>
        <p class="description">Use para redes como Lomadee, Afilio, onde o link de afiliado já vem formatado na planilha.</p>
    </td>
</tr>

<tr>
    <th>Ativa</th>
    <td><input type="checkbox" name="active" <?php checked($edit['active'] ?? 1, 1); ?>></td>
</tr>

<tr>
    <th><label for="notes">Notas</label></th>
    <td>
        <textarea id="notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea($edit['notes'] ?? ''); ?></textarea>
        <p class="description">Anotações internas (não afeta funcionamento).</p>
    </td>
</tr>

</table>

<p>
    <button class="button button-primary" name="ci_save_network">Salvar Rede</button>
    <?php if ($edit): ?>
    &nbsp; <a href="?page=coupon-importer-networks" class="button">Cancelar</a>
    <?php endif; ?>
</p>
</form>
</div>

<hr>
<h2>Redes Cadastradas</h2>

<table class="widefat striped">
<thead><tr>
    <th>Nome</th>
    <th>Publisher ID</th>
    <th>Template</th>
    <th>Link Direto</th>
    <th>Status</th>
    <th>Ações</th>
</tr></thead>
<tbody>
<?php foreach ($networks as $net): ?>
<tr>
    <td><strong><?php echo esc_html($net['name']); ?></strong>
        <?php if ($net['notes']): ?>
        <br><small style="color:#888;"><?php echo esc_html($net['notes']); ?></small>
        <?php endif; ?>
    </td>
    <td><?php echo esc_html($net['publisher_id'] ?: '—'); ?></td>
    <td style="font-size:11px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?php
        if ($net['link_direct']) {
            echo '<em style="color:#888;">link direto</em>';
        } elseif (strtolower($net['name']) === 'amazon') {
            $tag = esc_html($net['publisher_id'] ?: '—');
            echo '<em style="color:#2271b1;">?tag=' . $tag . '</em>';
        } else {
            echo esc_html($net['template'] ?: '—');
        }
        ?>
    </td>
    <td><?php echo $net['link_direct'] ? '✅' : '—'; ?></td>
    <td><?php echo $net['active'] ? 'Ativa' : '<span style="color:#888;">Inativa</span>'; ?></td>
    <td>
        <a href="?page=coupon-importer-networks&edit=<?php echo intval($net['id']); ?>">Editar</a> |
        <a href="<?php echo wp_nonce_url('?page=coupon-importer-networks&delete=' . intval($net['id']), 'ci_delete_network'); ?>"
           onclick="return confirm('Remover esta rede?')">Excluir</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
