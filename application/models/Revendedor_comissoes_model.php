<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Revendedor_comissoes_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obter comissões
     * @param int $id
     * @param array $where
     * @return array|object
     */
    public function get($id = '', $where = [])
    {
        $this->db->select('rc.*, r.nome as revendedor_nome, i.number as invoice_number, i.date as invoice_date');
        $this->db->from(db_prefix() . 'revendedor_comissoes rc');
        $this->db->join(db_prefix() . 'revendedores r', 'rc.revendedor_id = r.id', 'left');
        $this->db->join(db_prefix() . 'invoices i', 'rc.invoice_id = i.id', 'left');
        $this->db->where($where);
        
        if (is_numeric($id)) {
            $this->db->where('rc.id', $id);
            return $this->db->get()->row();
        }
        
        $this->db->order_by('rc.data_venda', 'DESC');
        return $this->db->get()->result_array();
    }

    /**
     * Calcular e registrar comissão
     * @param int $invoice_id
     * @return boolean
     */
    public function calcular_comissao($invoice_id)
    {
        // Obter dados da fatura
        $this->db->select('i.*, r.taxa_comissao');
        $this->db->from(db_prefix() . 'invoices i');
        $this->db->join(db_prefix() . 'revendedores r', 'i.revendedor_id = r.id', 'left');
        $this->db->where('i.id', $invoice_id);
        $invoice = $this->db->get()->row();
        
        if (!$invoice || !$invoice->revendedor_id) {
            return false;
        }
        
        // Verificar se comissão já foi calculada
        $this->db->where('invoice_id', $invoice_id);
        $comissao_existente = $this->db->get(db_prefix() . 'revendedor_comissoes')->row();
        
        if ($comissao_existente) {
            return $this->atualizar_comissao($comissao_existente->id, $invoice);
        }
        
        // Calcular comissão
        $valor_comissao = ($invoice->total * $invoice->taxa_comissao) / 100;
        
        $comissao_data = [
            'revendedor_id' => $invoice->revendedor_id,
            'invoice_id' => $invoice_id,
            'valor_venda' => $invoice->total,
            'percentual_comissao' => $invoice->taxa_comissao,
            'valor_comissao' => $valor_comissao,
            'status' => 'pendente',
            'data_venda' => $invoice->date
        ];
        
        $this->db->insert(db_prefix() . 'revendedor_comissoes', $comissao_data);
        $comissao_id = $this->db->insert_id();
        
        if ($comissao_id) {
            log_activity('Comissão calculada - Revendedor ID: ' . $invoice->revendedor_id . ', Valor: ' . app_format_money($valor_comissao, get_base_currency()));
            return $comissao_id;
        }
        
        return false;
    }

    /**
     * Atualizar comissão existente
     * @param int $comissao_id
     * @param object $invoice
     * @return boolean
     */
    private function atualizar_comissao($comissao_id, $invoice)
    {
        $valor_comissao = ($invoice->total * $invoice->taxa_comissao) / 100;
        
        $update_data = [
            'valor_venda' => $invoice->total,
            'percentual_comissao' => $invoice->taxa_comissao,
            'valor_comissao' => $valor_comissao
        ];
        
        $this->db->where('id', $comissao_id);
        $this->db->update(db_prefix() . 'revendedor_comissoes', $update_data);
        
        return $this->db->affected_rows() > 0;
    }

    /**
     * Marcar comissão como paga
     * @param int $comissao_id
     * @param string $observacoes
     * @return boolean
     */
    public function marcar_como_paga($comissao_id, $observacoes = null)
    {
        $update_data = [
            'status' => 'paga',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'observacoes' => $observacoes
        ];
        
        $this->db->where('id', $comissao_id);
        $this->db->update(db_prefix() . 'revendedor_comissoes', $update_data);
        
        if ($this->db->affected_rows() > 0) {
            $comissao = $this->get($comissao_id);
            log_activity('Comissão paga - ID: ' . $comissao_id . ', Revendedor: ' . $comissao->revendedor_nome . ', Valor: ' . app_format_money($comissao->valor_comissao, get_base_currency()));
            return true;
        }
        
        return false;
    }

    /**
     * Cancelar comissão
     * @param int $comissao_id
     * @param string $observacoes
     * @return boolean
     */
    public function cancelar_comissao($comissao_id, $observacoes = null)
    {
        $update_data = [
            'status' => 'cancelada',
            'observacoes' => $observacoes
        ];
        
        $this->db->where('id', $comissao_id);
        $this->db->update(db_prefix() . 'revendedor_comissoes', $update_data);
        
        if ($this->db->affected_rows() > 0) {
            $comissao = $this->get($comissao_id);
            log_activity('Comissão cancelada - ID: ' . $comissao_id . ', Revendedor: ' . $comissao->revendedor_nome);
            return true;
        }
        
        return false;
    }

    /**
     * Obter comissões por revendedor
     * @param int $revendedor_id
     * @param string $status
     * @return array
     */
    public function get_por_revendedor($revendedor_id, $status = null)
    {
        $where = ['rc.revendedor_id' => $revendedor_id];
        
        if ($status) {
            $where['rc.status'] = $status;
        }
        
        return $this->get('', $where);
    }

    /**
     * Obter total de comissões por status
     * @param int $revendedor_id
     * @param string $status
     * @return float
     */
    public function get_total_por_status($revendedor_id = null, $status = 'pendente')
    {
        $this->db->select('SUM(valor_comissao) as total');
        $this->db->where('status', $status);
        
        if ($revendedor_id) {
            $this->db->where('revendedor_id', $revendedor_id);
        }
        
        $result = $this->db->get(db_prefix() . 'revendedor_comissoes')->row();
        
        return $result ? (float)$result->total : 0;
    }

    /**
     * Obter relatório de comissões
     * @param array $filtros
     * @return array
     */
    public function get_relatorio($filtros = [])
    {
        $this->db->select('
            rc.*,
            r.nome as revendedor_nome,
            r.email as revendedor_email,
            i.number as invoice_number,
            i.date as invoice_date,
            c.company as cliente_nome
        ');
        $this->db->from(db_prefix() . 'revendedor_comissoes rc');
        $this->db->join(db_prefix() . 'revendedores r', 'rc.revendedor_id = r.id', 'left');
        $this->db->join(db_prefix() . 'invoices i', 'rc.invoice_id = i.id', 'left');
        $this->db->join(db_prefix() . 'clients c', 'i.clientid = c.userid', 'left');
        
        // Aplicar filtros
        if (!empty($filtros['revendedor_id'])) {
            $this->db->where('rc.revendedor_id', $filtros['revendedor_id']);
        }
        
        if (!empty($filtros['status'])) {
            $this->db->where('rc.status', $filtros['status']);
        }
        
        if (!empty($filtros['data_inicio'])) {
            $this->db->where('rc.data_venda >=', $filtros['data_inicio']);
        }
        
        if (!empty($filtros['data_fim'])) {
            $this->db->where('rc.data_venda <=', $filtros['data_fim']);
        }
        
        $this->db->order_by('rc.data_venda', 'DESC');
        $comissoes = $this->db->get()->result_array();
        
        // Calcular totais
        $total_vendas = 0;
        $total_comissoes = 0;
        $total_pendentes = 0;
        $total_pagas = 0;
        
        foreach ($comissoes as $comissao) {
            $total_vendas += $comissao['valor_venda'];
            $total_comissoes += $comissao['valor_comissao'];
            
            if ($comissao['status'] == 'pendente') {
                $total_pendentes += $comissao['valor_comissao'];
            } elseif ($comissao['status'] == 'paga') {
                $total_pagas += $comissao['valor_comissao'];
            }
        }
        
        return [
            'comissoes' => $comissoes,
            'resumo' => [
                'total_vendas' => $total_vendas,
                'total_comissoes' => $total_comissoes,
                'total_pendentes' => $total_pendentes,
                'total_pagas' => $total_pagas,
                'quantidade' => count($comissoes)
            ]
        ];
    }

    /**
     * Obter comissões do mês
     * @param int $revendedor_id
     * @param int $mes
     * @param int $ano
     * @return array
     */
    public function get_comissoes_mes($revendedor_id = null, $mes = null, $ano = null)
    {
        $mes = $mes ?: date('m');
        $ano = $ano ?: date('Y');
        
        $this->db->select('SUM(valor_comissao) as total, COUNT(*) as quantidade');
        $this->db->where('MONTH(data_venda)', $mes);
        $this->db->where('YEAR(data_venda)', $ano);
        $this->db->where('status !=', 'cancelada');
        
        if ($revendedor_id) {
            $this->db->where('revendedor_id', $revendedor_id);
        }
        
        $result = $this->db->get(db_prefix() . 'revendedor_comissoes')->row();
        
        return [
            'total' => $result ? (float)$result->total : 0,
            'quantidade' => $result ? (int)$result->quantidade : 0
        ];
    }

    /**
     * Processar pagamento em lote
     * @param array $comissao_ids
     * @param string $observacoes
     * @return boolean
     */
    public function pagar_lote($comissao_ids, $observacoes = null)
    {
        if (empty($comissao_ids)) {
            return false;
        }
        
        $update_data = [
            'status' => 'paga',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'observacoes' => $observacoes
        ];
        
        $this->db->where_in('id', $comissao_ids);
        $this->db->where('status', 'pendente'); // Só pagar as pendentes
        $this->db->update(db_prefix() . 'revendedor_comissoes', $update_data);
        
        $affected = $this->db->affected_rows();
        
        if ($affected > 0) {
            log_activity('Pagamento em lote de comissões - ' . $affected . ' comissões pagas');
            return true;
        }
        
        return false;
    }

    /**
     * Obter dashboard de comissões
     * @param int $revendedor_id
     * @return array
     */
    public function get_dashboard_comissoes($revendedor_id = null)
    {
        $data = [];
        
        // Comissões pendentes
        $data['pendentes'] = $this->get_total_por_status($revendedor_id, 'pendente');
        
        // Comissões pagas
        $data['pagas'] = $this->get_total_por_status($revendedor_id, 'paga');
        
        // Comissões do mês atual
        $comissoes_mes = $this->get_comissoes_mes($revendedor_id);
        $data['mes_atual'] = $comissoes_mes['total'];
        
        // Comissões do mês anterior
        $mes_anterior = date('m', strtotime('-1 month'));
        $ano_anterior = date('Y', strtotime('-1 month'));
        $comissoes_mes_anterior = $this->get_comissoes_mes($revendedor_id, $mes_anterior, $ano_anterior);
        $data['mes_anterior'] = $comissoes_mes_anterior['total'];
        
        // Crescimento
        $data['crescimento'] = 0;
        if ($data['mes_anterior'] > 0) {
            $data['crescimento'] = (($data['mes_atual'] - $data['mes_anterior']) / $data['mes_anterior']) * 100;
        }
        
        return $data;
    }
}