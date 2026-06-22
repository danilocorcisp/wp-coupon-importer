<?php
if (!defined('ABSPATH')) exit;

// Salva configurações
if (isset($_POST['ci_save_settings'])) {
    check_admin_referer('ci_settings');
    for ($i = 1; $i <= 5; $i++) {
        $url = esc_url_raw($_POST["ci_sheet_url_{$i}"] ?? '');
        if ($url) update_option("ci_sheet_url_{$i}", $url);
        else delete_option("ci_sheet_url_{$i}");
    }
    update_option('ci_batch_size',    intval($_POST['ci_batch_size'] ?? 200));
    update_option('ci_import_mode',   sanitize_text_field($_POST['ci_import_mode'] ?? 'new_only'));
    echo '<div class="updated"><p>Configurações salvas.</p></div>';
}

$batch_size  = get_option('ci_batch_size', 200);
$import_mode = get_option('ci_import_mode', 'new_only');
?>
<div class="wrap">
<h1>Coupon Importer</h1>

<!-- CONFIGURAÇÕES -->
<div class="card" style="max-width:820px;padding:20px;">
<h2 style="margin-top:0;">⚙️ Configurações</h2>
<form method="post">
<?php wp_nonce_field('ci_settings'); ?>

<table class="form-table">
<?php for ($i = 1; $i <= 5; $i++):
    $url = get_option("ci_sheet_url_{$i}", '');
?>
<tr>
    <th><label for="ci_sheet_url_<?php echo $i; ?>">URL da Planilha <?php echo $i; ?><?php echo $i > 1 ? ' <span style="font-weight:normal;color:#888;">(opcional)</span>' : ''; ?></label></th>
    <td><input type="url" id="ci_sheet_url_<?php echo $i; ?>" name="ci_sheet_url_<?php echo $i; ?>" value="<?php echo esc_attr($url); ?>" class="large-text" placeholder="https://docs.google.com/spreadsheets/..."></td>
</tr>
<?php endfor; ?>

<tr>
    <th><label for="ci_batch_size">Tamanho do Lote</label></th>
    <td>
        <input type="number" id="ci_batch_size" name="ci_batch_size" value="<?php echo esc_attr($batch_size); ?>" min="10" max="1000" style="width:100px;">
        <p class="description">Cupons processados por execução (padrão: 200)</p>
    </td>
</tr>

<tr>
    <th><label for="ci_import_mode">Modo Padrão</label></th>
    <td>
        <select id="ci_import_mode" name="ci_import_mode">
            <option value="new_only" <?php selected($import_mode, 'new_only'); ?>>Apenas Novos</option>
            <option value="update"   <?php selected($import_mode, 'update'); ?>>Atualiza + Cria</option>
        </select>
    </td>
</tr>
</table>

<p><button class="button button-primary" name="ci_save_settings">Salvar Configurações</button></p>
</form>
</div>

<hr>

<!-- IMPORTAÇÃO -->
<div class="card" style="max-width:820px;padding:20px;">
<h2 style="margin-top:0;">▶️ Importar</h2>

<p style="color:#646970;">
    As planilhas configuradas acima são importadas automaticamente.<br>
    Use o campo abaixo para importar um CSV avulso (upload ou colar).
</p>

<div style="margin-bottom:12px;">
    <label><strong>Carregar arquivo CSV:</strong></label><br>
    <input type="file" id="ci-file" accept=".csv" style="margin-top:5px;">
</div>

<div style="margin-bottom:12px;">
    <label><strong>Ou cole o conteúdo CSV:</strong></label><br>
    <textarea id="ci-csv" rows="6" style="width:100%;font-family:monospace;font-size:12px;" placeholder="Cole o CSV aqui (opcional — deixe vazio para usar só as planilhas configuradas)..."></textarea>
</div>

<div style="margin-bottom:16px;">
    <label><strong>Modo:</strong></label>
    <select id="ci-mode" style="margin-left:8px;">
        <option value="new_only" <?php selected($import_mode, 'new_only'); ?>>Apenas Novos</option>
        <option value="update"   <?php selected($import_mode, 'update'); ?>>Atualiza + Cria</option>
    </select>
</div>

<div>
    <button type="button" class="button button-secondary" id="ci-dry-run">🔍 Pré-visualizar</button>
    &nbsp;
    <button type="button" class="button button-primary" id="ci-import">▶️ Importar</button>
</div>

<div id="ci-progress" style="display:none;margin:16px 0;">
    <div style="background:#f0f0f1;border:1px solid #c3c4c7;border-radius:4px;padding:4px;height:28px;">
        <div id="ci-bar" style="background:linear-gradient(to right,#2271b1,#135e96);height:100%;width:0%;border-radius:2px;transition:width .3s;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:13px;">
            <span id="ci-pct">0%</span>
        </div>
    </div>
    <p id="ci-status" style="text-align:center;margin:8px 0 0;"></p>
</div>

<div id="ci-result" style="display:none;margin-top:20px;"></div>
</div>
</div>

<style>
.ci-stat { display:inline-block;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:14px 20px;margin:8px 8px 8px 0;text-align:center;min-width:110px; }
.ci-stat-n { font-size:30px;font-weight:bold;color:#2271b1;line-height:1; }
.ci-stat-l { color:#646970;font-size:12px;margin-top:4px; }
.ci-preview { background:#f6f7f7;padding:10px;margin:5px 0;border-left:3px solid #2271b1;font-size:13px; }
</style>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce('lsci_nonce'); ?>';

    $('#ci-file').on('change', function() {
        var f = this.files[0]; if (!f) return;
        var r = new FileReader();
        r.onload = function(e) { $('#ci-csv').val(e.target.result); };
        r.readAsText(f, 'UTF-8');
    });

    function disableBtn() { $('#ci-dry-run,#ci-import').prop('disabled', true); }
    function enableBtn()  { $('#ci-dry-run,#ci-import').prop('disabled', false); }
    function setProgress(pct, msg) {
        $('#ci-progress').show();
        $('#ci-bar').css('width', pct + '%');
        $('#ci-pct').text(Math.round(pct) + '%');
        if (msg) $('#ci-status').text(msg);
    }
    function hideProgress() { $('#ci-progress').hide(); $('#ci-bar').css('width','0%'); }
    function showResult(html) { $('#ci-result').html(html).show(); }

    function buildStats(data, dry) {
        var n = dry ? data.to_create : data.processed;
        var u = dry ? data.to_update : data.updated;
        var html = dry
            ? '<div class="notice notice-info"><p><strong>✅ Pré-visualização</strong></p></div>'
            : '<div class="notice notice-success"><p><strong>✅ Importação concluída!</strong></p></div>';

        html += '<div>';
        html += '<div class="ci-stat"><div class="ci-stat-n">' + n + '</div><div class="ci-stat-l">Novos</div></div>';
        html += '<div class="ci-stat"><div class="ci-stat-n">' + u + '</div><div class="ci-stat-l">Atualizados</div></div>';
        html += '<div class="ci-stat"><div class="ci-stat-n">' + data.ignored + '</div><div class="ci-stat-l">Ignorados</div></div>';
        html += '</div>';

        if (dry && data.preview_create && data.preview_create.length) {
            html += '<h3>Novos a importar (' + data.preview_create.length + '):</h3>';
            data.preview_create.forEach(function(item) {
                html += '<div class="ci-preview"><strong>' + item.description + '</strong><br>Código: ' + (item.code||'—') + ' | Loja: ' + item.advertiser + ' | Tipo: ' + item.coupon_type + '</div>';
            });
        }

        if (data.debug && data.debug.length) {
            html += '<details open style="margin-top:16px;"><summary><strong>Debug (' + data.debug.length + ' mensagens)</strong></summary>';
            html += '<pre style="max-height:300px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #ddd;font-size:12px;">';
            data.debug.forEach(function(l) { html += l + '\n'; });
            html += '</pre></details>';
        }
        return html;
    }

    function doRequest(action, label, confirm_msg) {
        if (confirm_msg && !confirm(confirm_msg)) return;
        disableBtn();
        setProgress(40, label + '...');
        showResult('<p style="color:#888;">Processando...</p>');

        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: action, mode: $('#ci-mode').val(), nonce: nonce, csv_content: $('#ci-csv').val() },
            success: function(r) {
                setProgress(100);
                showResult(r.success ? buildStats(r.data, action === 'lsci_preview') : '<div class="notice notice-error"><p>Erro: ' + (r.data.error||'desconhecido') + '</p></div>');
                hideProgress(); enableBtn();
            },
            error: function() {
                showResult('<div class="notice notice-error"><p>Erro de conexão.</p></div>');
                hideProgress(); enableBtn();
            }
        });
    }

    $('#ci-dry-run').on('click', function() { doRequest('lsci_preview', 'Analisando'); });
    $('#ci-import').on('click',  function() { doRequest('lsci_sync',  'Importando', 'Confirma a importação?'); });
});
</script>
