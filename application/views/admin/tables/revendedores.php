<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'id',
    'nome',
    'email',
    'telefone',
    'taxa_comissao',
    'total_vendas',
    'total_comissoes',
    'ativo',
    'data_cadastro'
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'revendedores';

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], ['id']);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    
    $row[] = $aRow['id'];
    $row[] = '<a href="' . admin_url('revendedores/revendedor/' . $aRow['id']) . '">' . $aRow['nome'] . '</a>';
    $row[] = $aRow['email'];
    $row[] = $aRow['telefone'];
    $row[] = $aRow['taxa_comissao'] . '%';
    
    // Calcular total de vendas
    $CI = &get_instance();
    $CI->load->model('revendedores_model');
    $total_vendas = $CI->revendedores_model->get_total_vendas($aRow['id']);
    $row[] = app_format_money($total_vendas, get_base_currency());
    
    // Calcular comissões pendentes
    $CI->load->model('revendedor_comissoes_model');
    $comissoes_pendentes = $CI->revendedor_comissoes_model->get_total_por_status($aRow['id'], 'pendente');
    $row[] = app_format_money($comissoes_pendentes, get_base_currency());
    
    $status = $aRow['ativo'] == '1' ? 
        '<span class="label label-success">Ativo</span>' : 
        '<span class="label label-danger">Inativo</span>';
    $row[] = $status;
    
    $row[] = _dt($aRow['data_cadastro']);
    
    $options = '<div class="row-options">';
    
    if (has_permission('revendedores', '', 'view')) {
        $options .= '<a href="' . admin_url('revendedores/dashboard/' . $aRow['id']) . '" class="btn btn-default btn-icon"><i class="fa fa-dashboard"></i></a>';
        $options .= '<a href="' . admin_url('revendedores/revendedor/' . $aRow['id']) . '" class="btn btn-default btn-icon"><i class="fa fa-pencil-square-o"></i></a>';
        $options .= '<a href="' . admin_url('revendedores/estoque/' . $aRow['id']) . '" class="btn btn-default btn-icon"><i class="fa fa-cubes"></i></a>';
        $options .= '<a href="' . admin_url('revendedores/comissoes/' . $aRow['id']) . '" class="btn btn-default btn-icon"><i class="fa fa-money"></i></a>';
    }
    
    if (has_permission('revendedores', '', 'delete')) {
        $options .= '<a href="' . admin_url('revendedores/delete/' . $aRow['id']) . '" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>';
    }
    
    $options .= '</div>';
    
    $row[] = $options;
    
    $output['aaData'][] = $row;
}

echo json_encode($output);
die();