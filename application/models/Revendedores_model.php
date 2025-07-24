<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Revendedores_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obter revendedor(es)
     * @param  mixed $id
     * @param  array $where
     * @return array|object
     */
    public function get($id = '', $where = [])
    {
        $this->db->select('r.*, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'revendedores r');
        $this->db->join(db_prefix() . 'staff s', 'r.staff_id = s.staffid', 'left');
        $this->db->where($where);
        
        if (is_numeric($id)) {
            $this->db->where('r.id', $id);
            $revendedor = $this->db->get()->row();
            
            if ($revendedor) {
                $revendedor->total_vendas = $this->get_total_vendas($id);
                $revendedor->total_comissoes = $this->get_total_comissoes($id);
                $revendedor->clientes_count = $this->get_clientes_count($id);
                $revendedor->vendas_mes_atual = $this->get_vendas_mes_atual($id);
            }
            
            return $revendedor;
        }
        
        $this->db->where('r.ativo', 1);
        $this->db->order_by('r.nome', 'ASC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Adicionar novo revendedor
     * @param array $data
     * @return mixed
     */
    public function add($data)
    {
        $data['data_cadastro'] = date('Y-m-d H:i:s');
        
        if (empty($data['taxa_comissao'])) {
            $data['taxa_comissao'] = get_option('revendedores_comissao_padrao');
        }
        
        if (empty($data['meta_mensal'])) {
            $data['meta_mensal'] = get_option('revendedores_meta_padrao');
        }
        
        $this->db->insert(db_prefix() . 'revendedores', $data);
        $insert_id = $this->db->insert_id();
        
        if ($insert_id) {
            // Log da atividade
            log_activity('Novo revendedor adicionado [Nome: ' . $data['nome'] . ', ID: ' . $insert_id . ']');
            
            // Criar estoque inicial para o revendedor
            $this->criar_estoque_inicial($insert_id);
        }
        
        return $insert_id;
    }

    /**
     * Atualizar revendedor
     * @param  mixed $id
     * @param  array $data
     * @return boolean
     */
    public function update($id, $data)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'revendedores', $data);
        
        if ($this->db->affected_rows() > 0) {
            log_activity('Revendedor atualizado [ID: ' . $id . ']');
            return true;
        }
        
        return false;
    }

    /**
     * Deletar revendedor
     * @param  mixed $id
     * @return boolean
     */
    public function delete($id)
    {
        $revendedor = $this->get($id);
        
        if (!$revendedor) {
            return false;
        }
        
        // Verificar se há vendas associadas
        $this->db->where('revendedor_id', $id);
        $invoices = $this->db->get(db_prefix() . 'invoices')->num_rows();
        
        if ($invoices > 0) {
            return ['error' => 'Não é possível excluir este revendedor pois há vendas associadas a ele.'];
        }
        
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'revendedores');
        
        if ($this->db->affected_rows() > 0) {
            log_activity('Revendedor excluído [Nome: ' . $revendedor->nome . ', ID: ' . $id . ']');
            return true;
        }
        
        return false;
    }

    /**
     * Obter total de vendas do revendedor
     * @param  int $revendedor_id
     * @return float
     */
    public function get_total_vendas($revendedor_id)
    {
        $this->db->select('SUM(total) as total');
        $this->db->where('revendedor_id', $revendedor_id);
        $this->db->where_not_in('status', [5, 6]); // Não incluir canceladas e rascunho
        $result = $this->db->get(db_prefix() . 'invoices')->row();
        
        return $result ? (float)$result->total : 0;
    }

    /**
     * Obter total de comissões do revendedor
     * @param  int $revendedor_id
     * @return float
     */
    public function get_total_comissoes($revendedor_id)
    {
        $this->db->select('SUM(valor_comissao) as total');
        $this->db->where('revendedor_id', $revendedor_id);
        $this->db->where('status', 'pendente');
        $result = $this->db->get(db_prefix() . 'revendedor_comissoes')->row();
        
        return $result ? (float)$result->total : 0;
    }

    /**
     * Obter número de clientes do revendedor
     * @param  int $revendedor_id
     * @return int
     */
    public function get_clientes_count($revendedor_id)
    {
        $this->db->where('revendedor_id', $revendedor_id);
        return $this->db->count_all_results(db_prefix() . 'clients');
    }

    /**
     * Obter vendas do mês atual
     * @param  int $revendedor_id
     * @return float
     */
    public function get_vendas_mes_atual($revendedor_id)
    {
        $this->db->select('SUM(total) as total');
        $this->db->where('revendedor_id', $revendedor_id);
        $this->db->where('MONTH(date)', date('m'));
        $this->db->where('YEAR(date)', date('Y'));
        $this->db->where_not_in('status', [5, 6]);
        $result = $this->db->get(db_prefix() . 'invoices')->row();
        
        return $result ? (float)$result->total : 0;
    }

    /**
     * Criar estoque inicial para o revendedor
     * @param int $revendedor_id
     */
    private function criar_estoque_inicial($revendedor_id)
    {
        // Obter todos os itens ativos
        $this->db->where('active', 1);
        $items = $this->db->get(db_prefix() . 'items')->result();
        
        $quantidade_minima_padrao = get_option('revendedores_quantidade_minima_padrao');
        
        foreach ($items as $item) {
            $estoque_data = [
                'revendedor_id' => $revendedor_id,
                'item_id' => $item->id,
                'quantidade' => 0,
                'quantidade_minima' => $quantidade_minima_padrao,
                'preco_custo' => 0,
                'preco_venda' => $item->rate
            ];
            
            $this->db->insert(db_prefix() . 'revendedor_estoque', $estoque_data);
        }
    }

    /**
     * Obter revendedores para dropdown
     * @return array
     */
    public function get_dropdown()
    {
        $this->db->select('id, nome');
        $this->db->where('ativo', 1);
        $this->db->order_by('nome', 'ASC');
        $revendedores = $this->db->get(db_prefix() . 'revendedores')->result_array();
        
        $dropdown = [];
        foreach ($revendedores as $revendedor) {
            $dropdown[$revendedor['id']] = $revendedor['nome'];
        }
        
        return $dropdown;
    }

    /**
     * Obter dashboard data para revendedor
     * @param int $revendedor_id
     * @return array
     */
    public function get_dashboard_data($revendedor_id)
    {
        $data = [];
        
        // Vendas do mês
        $data['vendas_mes'] = $this->get_vendas_mes_atual($revendedor_id);
        
        // Meta do mês
        $revendedor = $this->get($revendedor_id);
        $data['meta_mes'] = $revendedor ? $revendedor->meta_mensal : 0;
        
        // Percentual da meta
        $data['percentual_meta'] = $data['meta_mes'] > 0 ? ($data['vendas_mes'] / $data['meta_mes']) * 100 : 0;
        
        // Comissões pendentes
        $data['comissoes_pendentes'] = $this->get_total_comissoes($revendedor_id);
        
        // Clientes ativos
        $data['clientes_ativos'] = $this->get_clientes_count($revendedor_id);
        
        // Produtos em estoque baixo
        $this->db->select('COUNT(*) as count');
        $this->db->where('revendedor_id', $revendedor_id);
        $this->db->where('quantidade <= quantidade_minima');
        $result = $this->db->get(db_prefix() . 'revendedor_estoque')->row();
        $data['produtos_estoque_baixo'] = $result ? $result->count : 0;
        
        // Vendas últimos 12 meses
        $data['vendas_12_meses'] = $this->get_vendas_ultimos_meses($revendedor_id, 12);
        
        return $data;
    }

    /**
     * Obter vendas dos últimos meses
     * @param int $revendedor_id
     * @param int $meses
     * @return array
     */
    public function get_vendas_ultimos_meses($revendedor_id, $meses = 12)
    {
        $data = [];
        
        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes = date('m', strtotime("-$i months"));
            $ano = date('Y', strtotime("-$i months"));
            $mes_nome = date('M/Y', strtotime("-$i months"));
            
            $this->db->select('SUM(total) as total');
            $this->db->where('revendedor_id', $revendedor_id);
            $this->db->where('MONTH(date)', $mes);
            $this->db->where('YEAR(date)', $ano);
            $this->db->where_not_in('status', [5, 6]);
            $result = $this->db->get(db_prefix() . 'invoices')->row();
            
            $data[] = [
                'mes' => $mes_nome,
                'total' => $result ? (float)$result->total : 0
            ];
        }
        
        return $data;
    }

    /**
     * Verificar se email já existe
     * @param string $email
     * @param int $exclude_id
     * @return boolean
     */
    public function email_exists($email, $exclude_id = null)
    {
        $this->db->where('email', $email);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        
        return $this->db->count_all_results(db_prefix() . 'revendedores') > 0;
    }
}