<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Hooks para integração do sistema de revendedores
 */

// Hook após adicionar fatura
hooks()->add_action('after_invoice_added', 'revendedores_after_invoice_added');

// Hook após atualizar fatura
hooks()->add_action('after_invoice_updated', 'revendedores_after_invoice_updated');

// Hook após deletar fatura
hooks()->add_action('before_invoice_deleted', 'revendedores_before_invoice_deleted');

// Hook para adicionar campo revendedor no formulário de fatura
hooks()->add_action('after_invoice_view_as_client_link', 'revendedores_invoice_form_fields');

/**
 * Processar comissão após adicionar fatura
 */
function revendedores_after_invoice_added($invoice_id)
{
    $CI = &get_instance();
    $CI->load->model('revendedor_comissoes_model');
    $CI->load->model('revendedor_estoque_model');
    
    // Calcular comissão se houver revendedor associado
    $CI->revendedor_comissoes_model->calcular_comissao($invoice_id);
    
    // Processar baixa no estoque
    $CI->db->where('id', $invoice_id);
    $invoice = $CI->db->get(db_prefix() . 'invoices')->row();
    
    if ($invoice && $invoice->revendedor_id) {
        // Obter itens da fatura
        $items = get_items_by_type('invoice', $invoice_id);
        
        if (!empty($items)) {
            $resultado = $CI->revendedor_estoque_model->processar_venda($invoice->revendedor_id, $items, $invoice_id);
            
            if (isset($resultado['error'])) {
                log_activity('Erro ao processar estoque - Fatura #' . $invoice_id . ': ' . $resultado['error']);
            }
        }
    }
}

/**
 * Atualizar comissão após atualizar fatura
 */
function revendedores_after_invoice_updated($invoice_id)
{
    $CI = &get_instance();
    $CI->load->model('revendedor_comissoes_model');
    
    // Recalcular comissão
    $CI->revendedor_comissoes_model->calcular_comissao($invoice_id);
}

/**
 * Reverter estoque antes de deletar fatura
 */
function revendedores_before_invoice_deleted($invoice_id)
{
    $CI = &get_instance();
    $CI->load->model('revendedor_estoque_model');
    
    // Reverter movimentação de estoque
    $CI->revendedor_estoque_model->reverter_venda($invoice_id);
}

/**
 * Adicionar campos de revendedor no formulário de fatura
 */
function revendedores_invoice_form_fields()
{
    $CI = &get_instance();
    
    // Verificar se o módulo está ativo
    if (!get_option('revendedores_ativo')) {
        return;
    }
    
    $CI->load->model('revendedores_model');
    $revendedores = $CI->revendedores_model->get_dropdown();
    
    if (empty($revendedores)) {
        return;
    }
    
    $selected_revendedor = '';
    
    // Se estiver editando, obter revendedor atual
    if (isset($CI->invoice) && $CI->invoice) {
        $selected_revendedor = $CI->invoice->revendedor_id;
    }
    
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo render_select('revendedor_id', $revendedores, ['id', 'nome'], 'Revendedor', $selected_revendedor, [], [], '', '', false);
    echo '</div>';
    echo '</div>';
}

/**
 * Filtrar clientes por revendedor
 */
hooks()->add_filter('clients_where_sql', function($where) {
    $CI = &get_instance();
    
    // Verificar se é um revendedor logado
    if (is_staff_logged_in() && get_option('revendedores_ativo')) {
        $staff_id = get_staff_user_id();
        
        // Verificar se o staff é um revendedor
        $CI->db->where('staff_id', $staff_id);
        $revendedor = $CI->db->get(db_prefix() . 'revendedores')->row();
        
        if ($revendedor) {
            // Filtrar apenas clientes deste revendedor
            $where .= ' AND (revendedor_id = ' . $revendedor->id . ' OR revendedor_id IS NULL)';
        }
    }
    
    return $where;
});

/**
 * Adicionar colunas na listagem de faturas
 */
hooks()->add_filter('invoices_table_columns', function($columns) {
    if (get_option('revendedores_ativo')) {
        $columns[] = [
            'name' => 'revendedor_nome',
            'th' => 'Revendedor'
        ];
    }
    return $columns;
});

/**
 * Adicionar dados na listagem de faturas
 */
hooks()->add_filter('invoices_table_sql_select', function($select) {
    if (get_option('revendedores_ativo')) {
        $select .= ', r.nome as revendedor_nome';
    }
    return $select;
});

/**
 * Adicionar joins na listagem de faturas
 */
hooks()->add_filter('invoices_table_sql_joins', function($joins) {
    if (get_option('revendedores_ativo')) {
        $joins .= ' LEFT JOIN ' . db_prefix() . 'revendedores r ON ' . db_prefix() . 'invoices.revendedor_id = r.id';
    }
    return $joins;
});

/**
 * Adicionar menu de revendedores
 */
hooks()->add_action('admin_init', function() {
    if (get_option('revendedores_ativo') && has_permission('revendedores', '', 'view')) {
        $CI = &get_instance();
        
        // Adicionar item no menu principal
        $CI->app_menu->add_sidebar_menu_item('revendedores', [
            'name'     => 'Revendedores',
            'href'     => admin_url('revendedores'),
            'icon'     => 'fa fa-users',
            'position' => 35,
        ]);
        
        // Submenu
        $CI->app_menu->add_sidebar_children_item('revendedores', [
            'slug'     => 'revendedores-list',
            'name'     => 'Lista de Revendedores',
            'href'     => admin_url('revendedores'),
            'position' => 1,
        ]);
        
        $CI->app_menu->add_sidebar_children_item('revendedores', [
            'slug'     => 'revendedores-add',
            'name'     => 'Novo Revendedor',
            'href'     => admin_url('revendedores/revendedor'),
            'position' => 2,
        ]);
        
        $CI->app_menu->add_sidebar_children_item('revendedores', [
            'slug'     => 'revendedores-comissoes',
            'name'     => 'Comissões',
            'href'     => admin_url('revendedores/comissoes'),
            'position' => 3,
        ]);
        
        $CI->app_menu->add_sidebar_children_item('revendedores', [
            'slug'     => 'revendedores-relatorios',
            'name'     => 'Relatórios',
            'href'     => admin_url('revendedores/relatorios'),
            'position' => 4,
        ]);
        
        if (is_admin()) {
            $CI->app_menu->add_sidebar_children_item('revendedores', [
                'slug'     => 'revendedores-config',
                'name'     => 'Configurações',
                'href'     => admin_url('revendedores/configuracoes'),
                'position' => 5,
            ]);
        }
    }
});

/**
 * Adicionar permissões
 */
hooks()->add_action('admin_init', function() {
    $CI = &get_instance();
    
    // Registrar permissões para revendedores
    if (!$CI->db->table_exists(db_prefix() . 'staff_permissions')) {
        return;
    }
    
    $permissions = [
        [
            'name' => 'revendedores',
            'capabilities' => ['view', 'create', 'edit', 'delete']
        ]
    ];
    
    foreach ($permissions as $permission) {
        // Verificar se a permissão já existe
        $CI->db->where('name', $permission['name']);
        $exists = $CI->db->get(db_prefix() . 'staff_permissions')->num_rows();
        
        if ($exists == 0) {
            foreach ($permission['capabilities'] as $capability) {
                $CI->db->insert(db_prefix() . 'staff_permissions', [
                    'name' => $permission['name'],
                    'shortname' => $capability,
                ]);
            }
        }
    }
});

/**
 * Validar estoque antes de finalizar venda
 */
hooks()->add_filter('before_invoice_added', function($data) {
    $CI = &get_instance();
    
    if (!get_option('revendedores_ativo') || !isset($data['data']['revendedor_id']) || empty($data['data']['revendedor_id'])) {
        return $data;
    }
    
    $CI->load->model('revendedor_estoque_model');
    
    $revendedor_id = $data['data']['revendedor_id'];
    $items = $data['items'];
    
    // Validar se há estoque suficiente
    foreach ($items as $item) {
        if ($item['rel_type'] == 'item') {
            $estoque = $CI->revendedor_estoque_model->get($revendedor_id, $item['rel_id']);
            
            if (!$estoque || $estoque->quantidade < $item['qty']) {
                $CI->db->where('id', $item['rel_id']);
                $produto = $CI->db->get(db_prefix() . 'items')->row();
                
                $produto_nome = $produto ? $produto->description : 'Produto ID: ' . $item['rel_id'];
                
                // Definir erro para ser capturado pelo sistema
                $CI->session->set_flashdata('danger', 'Estoque insuficiente para o produto: ' . $produto_nome . '. Disponível: ' . ($estoque ? $estoque->quantidade : 0) . ', Solicitado: ' . $item['qty']);
                
                // Redirecionar de volta para o formulário
                redirect($_SERVER['HTTP_REFERER']);
            }
        }
    }
    
    return $data;
});