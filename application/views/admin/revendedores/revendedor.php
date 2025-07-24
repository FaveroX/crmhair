<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php echo form_open(admin_url('revendedores/revendedor' . (isset($revendedor) ? '/' . $revendedor->id : '')), ['id' => 'revendedor-form']); ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo render_input('nome', 'Nome *', isset($revendedor) ? $revendedor->nome : '', 'text', ['required' => true]); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_input('email', 'Email *', isset($revendedor) ? $revendedor->email : '', 'email', ['required' => true]); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_input('telefone', 'Telefone', isset($revendedor) ? $revendedor->telefone : ''); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_input('cpf_cnpj', 'CPF/CNPJ', isset($revendedor) ? $revendedor->cpf_cnpj : ''); ?>
                            </div>
                            <div class="col-md-6">
                                <?php 
                                $staff_options = [];
                                foreach ($staff_members as $staff) {
                                    $staff_options[$staff['staffid']] = $staff['firstname'] . ' ' . $staff['lastname'];
                                }
                                echo render_select('staff_id', $staff_options, [], 'Staff Vinculado', isset($revendedor) ? $revendedor->staff_id : ''); 
                                ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo render_textarea('endereco', 'Endereço', isset($revendedor) ? $revendedor->endereco : ''); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <?php echo render_input('cidade', 'Cidade', isset($revendedor) ? $revendedor->cidade : ''); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('estado', 'Estado', isset($revendedor) ? $revendedor->estado : ''); ?>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('cep', 'CEP', isset($revendedor) ? $revendedor->cep : ''); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_input('taxa_comissao', 'Taxa de Comissão (%)', isset($revendedor) ? $revendedor->taxa_comissao : get_option('revendedores_comissao_padrao'), 'number', ['step' => '0.01', 'min' => '0', 'max' => '100']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_input('meta_mensal', 'Meta Mensal (R$)', isset($revendedor) ? $revendedor->meta_mensal : get_option('revendedores_meta_padrao'), 'number', ['step' => '0.01', 'min' => '0']); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="ativo">Status</label>
                                    <select name="ativo" id="ativo" class="form-control">
                                        <option value="1" <?php echo (isset($revendedor) && $revendedor->ativo == 1) ? 'selected' : ''; ?>>Ativo</option>
                                        <option value="0" <?php echo (isset($revendedor) && $revendedor->ativo == 0) ? 'selected' : ''; ?>>Inativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo render_textarea('observacoes', 'Observações', isset($revendedor) ? $revendedor->observacoes : ''); ?>
                            </div>
                        </div>
                        
                        <hr />
                        
                        <button type="submit" class="btn btn-info pull-right">
                            <?php echo isset($revendedor) ? 'Atualizar' : 'Salvar'; ?>
                        </button>
                        
                        <a href="<?php echo admin_url('revendedores'); ?>" class="btn btn-default">
                            Cancelar
                        </a>
                        
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    appValidateForm($('#revendedor-form'), {
        nome: 'required',
        email: {
            required: true,
            email: true
        },
        taxa_comissao: {
            required: true,
            number: true,
            min: 0,
            max: 100
        },
        meta_mensal: {
            required: true,
            number: true,
            min: 0
        }
    });
});
</script>

<?php init_tail(); ?>