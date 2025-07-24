<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="_buttons">
                            <?php if (has_permission('revendedores', '', 'create')) { ?>
                                <a href="<?php echo admin_url('revendedores/revendedor'); ?>" class="btn btn-info pull-left display-block">
                                    <i class="fa fa-plus-circle"></i>
                                    Novo Revendedor
                                </a>
                            <?php } ?>
                            
                            <div class="btn-group pull-right">
                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-download"></i> Exportar <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a href="<?php echo admin_url('revendedores/export/revendedores'); ?>">Lista de Revendedores</a></li>
                                    <li><a href="<?php echo admin_url('revendedores/export/comissoes'); ?>">Comissões</a></li>
                                </ul>
                            </div>
                            
                            <div class="clearfix"></div>
                        </div>
                        
                        <hr class="hr-panel-heading" />
                        
                        <div class="clearfix"></div>
                        
                        <?php render_datatable([
                            _l('id'),
                            'Nome',
                            'Email',
                            'Telefone',
                            'Taxa Comissão (%)',
                            'Total Vendas',
                            'Comissões Pendentes',
                            'Status',
                            'Data Cadastro',
                            _l('options'),
                        ], 'revendedores'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    initDataTable('.table-revendedores', window.location.href, [9], [9], [], [0, 'desc']);
});
</script>

<?php init_tail(); ?>