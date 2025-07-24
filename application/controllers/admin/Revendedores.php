<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Revendedores extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('revendedores_model');
        $this->load->model('revendedor_estoque_model');
        $this->load->model('revendedor_comissoes_model');
    }

    /**
     * Lista de revendedores
     */
    public function index()
    {
        if (!has_permission('revendedores', '', 'view')) {
            access_denied('revendedores');
        }

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('revendedores', 'admin/tables/revendedores'));
        }

        $data['title'] = _l('revendedores');
        $this->load->view('admin/revendedores/manage', $data);
    }

    /**
     * Adicionar/Editar revendedor
     */
    public function revendedor($id = '')
    {
        if (!has_permission('revendedores', '', 'view')) {
            access_denied('revendedores');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            
            if ($id == '') {
                if (!has_permission('revendedores', '', 'create')) {
                    access_denied('revendedores');
                }

                // Validar email único
                if ($this->revendedores_model->email_exists($data['email'])) {
                    set_alert('danger', 'Email já está sendo usado por outro revendedor');
                    redirect(admin_url('revendedores/revendedor'));
                }

                $id = $this->revendedores_model->add($data);
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('revendedor')));
                    redirect(admin_url('revendedores/revendedor/' . $id));
                }
            } else {
                if (!has_permission('revendedores', '', 'edit')) {
                    access_denied('revendedores');
                }

                // Validar email único
                if ($this->revendedores_model->email_exists($data['email'], $id)) {
                    set_alert('danger', 'Email já está sendo usado por outro revendedor');
                    redirect(admin_url('revendedores/revendedor/' . $id));
                }

                $success = $this->revendedores_model->update($id, $data);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('revendedor')));
                }
                redirect(admin_url('revendedores/revendedor/' . $id));
            }
        }

        if ($id == '') {
            $title = _l('add_new', _l('revendedor'));
        } else {
            $data['revendedor'] = $this->revendedores_model->get($id);

            if (!$data['revendedor']) {
                blank_page();
            }

            $title = _l('edit', _l('revendedor'));
        }

        // Obter staff para dropdown
        $this->load->model('staff_model');
        $data['staff_members'] = $this->staff_model->get('', ['active' => 1]);
        
        $data['title'] = $title;
        $this->load->view('admin/revendedores/revendedor', $data);
    }

    /**
     * Deletar revendedor
     */
    public function delete($id)
    {
        if (!has_permission('revendedores', '', 'delete')) {
            access_denied('revendedores');
        }

        if (!$id) {
            redirect(admin_url('revendedores'));
        }

        $response = $this->revendedores_model->delete($id);
        
        if (is_array($response) && isset($response['error'])) {
            set_alert('danger', $response['error']);
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('revendedor')));
        } else {
            set_alert('danger', _l('problem_deleting', _l('revendedor')));
        }

        redirect(admin_url('revendedores'));
    }

    /**
     * Dashboard do revendedor
     */
    public function dashboard($id)
    {
        if (!has_permission('revendedores', '', 'view')) {
            access_denied('revendedores');
        }

        $data['revendedor'] = $this->revendedores_model->get($id);
        
        if (!$data['revendedor']) {
            blank_page();
        }

        $data['dashboard_data'] = $this->revendedores_model->get_dashboard_data($id);
        $data['estoque_baixo'] = $this->revendedor_estoque_model->get_estoque_baixo($id);
        $data['comissoes_recentes'] = $this->revendedor_comissoes_model->get_por_revendedor($id);
        
        $data['title'] = 'Dashboard - ' . $data['revendedor']->nome;
        $this->load->view('admin/revendedores/dashboard', $data);
    }

    /**
     * Gerenciar estoque do revendedor
     */
    public function estoque($id)
    {
        if (!has_permission('revendedores', '', 'view')) {
            access_denied('revendedores');
        }

        $data['revendedor'] = $this->revendedores_model->get($id);
        
        if (!$data['revendedor']) {
            blank_page();
        }

        if ($this->input->post()) {
            $post_data = $this->input->post();
            
            foreach ($post_data['estoque'] as $item_id => $item_data) {
                $this->revendedor_estoque_model->update($id, $item_id, $item_data);
            }
            
            set_alert('success', 'Estoque atualizado com sucesso');
            redirect(admin_url('revendedores/estoque/' . $id));
        }

        $data['estoque'] = $this->revendedor_estoque_model->get($id);
        $data['movimentacoes'] = $this->revendedor_estoque_model->get_movimentacao($id, null, 20);
        
        $data['title'] = 'Estoque - ' . $data['revendedor']->nome;
        $this->load->view('admin/revendedores/estoque', $data);
    }

    /**
     * Movimentar estoque
     */
    public function movimentar_estoque()
    {
        if (!has_permission('revendedores', '', 'edit')) {
            access_denied('revendedores');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            
            $resultado = $this->revendedor_estoque_model->movimentar(
                $data['revendedor_id'],
                $data['item_id'],
                $data['quantidade'],
                $data['tipo'],
                $data['motivo']
            );
            
            if (isset($resultado['error'])) {
                echo json_encode(['success' => false, 'message' => $resultado['error']]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Estoque movimentado com sucesso']);
            }
            return;
        }
    }

    /**
     * Comissões do revendedor
     */
    public function comissoes($id = '')
    {
        if (!has_permission('revendedores', '', 'view')) {
            access_denied('revendedores');
        }

        $data = [];
        
        if ($id) {
            $data['revendedor'] = $this->revendedores_model->get($id);
            if (!$data['revendedor']) {
                blank_page();
            }
            $data['title'] = 'Comissões - ' . $data['revendedor']->nome;
        } else {
            $data['title'] = 'Comissões - Todos os Revendedores';
        }

        // Filtros
        $filtros = [];
        if ($id) {
            $filtros['revendedor_id'] = $id;
        }
        
        if ($this->input->get('status')) {
            $filtros['status'] = $this->input->get('status');
        }
        
        if ($this->input->get('data_inicio')) {
            $filtros['data_inicio'] = $this->input->get('data_inicio');
        }
        
        if ($this->input->get('data_fim')) {
            $filtros['data_fim'] = $this->input->get('data_fim');
        }

        $data['relatorio'] = $this->revendedor_comissoes_model->get_relatorio($filtros);
        $data['revendedores'] = $this->revendedores_model->get_dropdown();
        
        $this->load->view('admin/revendedores/comissoes', $data);
    }

    /**
     * Pagar comissão
     */
    public function pagar_comissao($id)
    {
        if (!has_permission('revendedores', '', 'edit')) {
            access_denied('revendedores');
        }

        $observacoes = $this->input->post('observacoes');
        $success = $this->revendedor_comissoes_model->marcar_como_paga($id, $observacoes);
        
        if ($success) {
            set_alert('success', 'Comissão marcada como paga');
        } else {
            set_alert('danger', 'Erro ao processar pagamento da comissão');
        }

        redirect($this->input->server('HTTP_REFERER'));
    }

    /**
     * Cancelar comissão
     */
    public function cancelar_comissao($id)
    {
        if (!has_permission('revendedores', '', 'delete')) {
            access_denied('revendedores');
        }

        $observacoes = $this->input->post('observacoes');
        $success = $this->revendedor_comissoes_model->cancelar_comissao($id, $observacoes);
        
        if ($success) {
            set_alert('success', 'Comissão cancelada');
        } else {
            set_alert('danger', 'Erro ao cancelar comissão');
        }

        redirect($this->input->server('HTTP_REFERER'));
    }

    /**
     * Pagar comissões em lote
     */
    public function pagar_lote()
    {
        if (!has_permission('revendedores', '', 'edit')) {
            access_denied('revendedores');
        }

        $comissao_ids = $this->input->post('comissao_ids');
        $observacoes = $this->input->post('observacoes');
        
        if (empty($comissao_ids)) {
            set_alert('danger', 'Nenhuma comissão selecionada');
            redirect($this->input->server('HTTP_REFERER'));
        }

        $success = $this->revendedor_comissoes_model->pagar_lote($comissao_ids, $observacoes);
        
        if ($success) {
            set_alert('success', 'Comissões pagas com sucesso');
        } else {
            set_alert('danger', 'Erro ao processar pagamento das comissões');
        }

        redirect($this->input->server('HTTP_REFERER'));
    }

    /**
     * Relatórios
     */
    public function relatorios()
    {
        if (!has_permission('revendedores', '', 'view')) {
            access_denied('revendedores');
        }

        $data['title'] = 'Relatórios - Revendedores';
        $data['revendedores'] = $this->revendedores_model->get();
        
        // Dados para gráficos
        $data['vendas_por_revendedor'] = $this->get_vendas_por_revendedor();
        $data['comissoes_por_mes'] = $this->get_comissoes_por_mes();
        
        $this->load->view('admin/revendedores/relatorios', $data);
    }

    /**
     * Obter vendas por revendedor para gráfico
     */
    private function get_vendas_por_revendedor()
    {
        $revendedores = $this->revendedores_model->get();
        $data = [];
        
        foreach ($revendedores as $revendedor) {
            $data[] = [
                'nome' => $revendedor['nome'],
                'vendas' => $this->revendedores_model->get_total_vendas($revendedor['id'])
            ];
        }
        
        return $data;
    }

    /**
     * Obter comissões por mês para gráfico
     */
    private function get_comissoes_por_mes()
    {
        $data = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $mes = date('m', strtotime("-$i months"));
            $ano = date('Y', strtotime("-$i months"));
            $mes_nome = date('M/Y', strtotime("-$i months"));
            
            $comissoes = $this->revendedor_comissoes_model->get_comissoes_mes(null, $mes, $ano);
            
            $data[] = [
                'mes' => $mes_nome,
                'total' => $comissoes['total']
            ];
        }
        
        return $data;
    }

    /**
     * Exportar dados
     */
    public function export($type = 'revendedores')
    {
        if (!has_permission('revendedores', '', 'view')) {
            access_denied('revendedores');
        }

        $this->load->library('excel');
        
        switch ($type) {
            case 'revendedores':
                $this->export_revendedores();
                break;
            case 'comissoes':
                $this->export_comissoes();
                break;
            case 'estoque':
                $this->export_estoque();
                break;
        }
    }

    /**
     * Exportar lista de revendedores
     */
    private function export_revendedores()
    {
        $revendedores = $this->revendedores_model->get();
        
        $this->excel->setActiveSheetIndex(0);
        $this->excel->getActiveSheet()->setTitle('Revendedores');
        
        // Cabeçalhos
        $headers = ['ID', 'Nome', 'Email', 'Telefone', 'CPF/CNPJ', 'Taxa Comissão', 'Meta Mensal', 'Status', 'Data Cadastro'];
        $col = 'A';
        foreach ($headers as $header) {
            $this->excel->getActiveSheet()->setCellValue($col . '1', $header);
            $col++;
        }
        
        // Dados
        $row = 2;
        foreach ($revendedores as $revendedor) {
            $this->excel->getActiveSheet()->setCellValue('A' . $row, $revendedor['id']);
            $this->excel->getActiveSheet()->setCellValue('B' . $row, $revendedor['nome']);
            $this->excel->getActiveSheet()->setCellValue('C' . $row, $revendedor['email']);
            $this->excel->getActiveSheet()->setCellValue('D' . $row, $revendedor['telefone']);
            $this->excel->getActiveSheet()->setCellValue('E' . $row, $revendedor['cpf_cnpj']);
            $this->excel->getActiveSheet()->setCellValue('F' . $row, $revendedor['taxa_comissao'] . '%');
            $this->excel->getActiveSheet()->setCellValue('G' . $row, app_format_money($revendedor['meta_mensal'], get_base_currency()));
            $this->excel->getActiveSheet()->setCellValue('H' . $row, $revendedor['ativo'] ? 'Ativo' : 'Inativo');
            $this->excel->getActiveSheet()->setCellValue('I' . $row, $revendedor['data_cadastro']);
            $row++;
        }
        
        $filename = 'revendedores_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel2007');
        $objWriter->save('php://output');
    }

    /**
     * Configurações do módulo
     */
    public function configuracoes()
    {
        if (!is_admin()) {
            access_denied('revendedores');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            
            foreach ($data as $key => $value) {
                update_option($key, $value);
            }
            
            set_alert('success', 'Configurações salvas com sucesso');
            redirect(admin_url('revendedores/configuracoes'));
        }

        $data['title'] = 'Configurações - Revendedores';
        $this->load->view('admin/revendedores/configuracoes', $data);
    }
}