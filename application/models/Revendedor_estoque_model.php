<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Revendedor_estoque_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obter estoque do revendedor
     * @param int $revendedor_id
     * @param int $item_id
     * @return array|object
     */
    public function get($revendedor_id, $item_id = null)
    {
        $this->db->select('re.*, i.description as item_name, i.long_description, i.unit');
        $this->db->from(db_prefix() . 'revendedor_estoque re');
        $this->db->join(db_prefix() . 'items i', 're.item_id = i.id', 'left');
        $this->db->where('re.revendedor_id', $revendedor_id);
        
        if ($item_id) {
            $this->db->where('re.item_id', $item_id);
            return $this->db->get()->row();
        }
        
        $this->db->order_by('i.description', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Atualizar estoque
     * @param int $revendedor_id
     * @param int $item_id
     * @param array $data
     * @return boolean
     */
    public function update($revendedor_id, $item_id, $data)
    {
        $this->db->where('revendedor_id', $revendedor_id);
        $this->db->where('item_id', $item_id);
        $this->db->update(db_prefix() . 'revendedor_estoque', $data);
        
        return $this->db->affected_rows() > 0;
    }

    /**
     * Movimentar estoque
     * @param int $revendedor_id
     * @param int $item_id
     * @param int $quantidade
     * @param string $tipo (entrada, saida, ajuste)
     * @param string $motivo
     * @param int $invoice_id
     * @return boolean
     */
    public function movimentar($revendedor_id, $item_id, $quantidade, $tipo, $motivo = null, $invoice_id = null)
    {
        // Obter quantidade atual
        $estoque_atual = $this->get($revendedor_id, $item_id);
        
        if (!$estoque_atual) {
            return false;
        }
        
        $quantidade_anterior = $estoque_atual->quantidade;
        
        // Calcular nova quantidade
        switch ($tipo) {
            case 'entrada':
                $nova_quantidade = $quantidade_anterior + $quantidade;
                break;
            case 'saida':
                $nova_quantidade = $quantidade_anterior - $quantidade;
                break;
            case 'ajuste':
                $nova_quantidade = $quantidade;
                $quantidade = $quantidade - $quantidade_anterior; // Diferença para o log
                break;
            default:
                return false;
        }
        
        // Não permitir estoque negativo
        if ($nova_quantidade < 0) {
            return ['error' => 'Estoque insuficiente para esta operação.'];
        }
        
        // Atualizar estoque
        $this->db->where('revendedor_id', $revendedor_id);
        $this->db->where('item_id', $item_id);
        $this->db->update(db_prefix() . 'revendedor_estoque', [
            'quantidade' => $nova_quantidade,
            'data_atualizacao' => date('Y-m-d H:i:s')
        ]);
        
        // Registrar movimentação
        $movimentacao_data = [
            'revendedor_id' => $revendedor_id,
            'item_id' => $item_id,
            'tipo_movimentacao' => $tipo,
            'quantidade' => abs($quantidade),
            'quantidade_anterior' => $quantidade_anterior,
            'quantidade_atual' => $nova_quantidade,
            'motivo' => $motivo,
            'invoice_id' => $invoice_id,
            'staff_id' => get_staff_user_id()
        ];
        
        $this->db->insert(db_prefix() . 'revendedor_estoque_movimentacao', $movimentacao_data);
        
        // Verificar se precisa notificar sobre estoque baixo
        if (get_option('revendedores_notificar_estoque_baixo') && $nova_quantidade <= $estoque_atual->quantidade_minima) {
            $this->notificar_estoque_baixo($revendedor_id, $item_id, $nova_quantidade);
        }
        
        return true;
    }

    /**
     * Obter produtos com estoque baixo
     * @param int $revendedor_id
     * @return array
     */
    public function get_estoque_baixo($revendedor_id)
    {
        $this->db->select('re.*, i.description as item_name');
        $this->db->from(db_prefix() . 'revendedor_estoque re');
        $this->db->join(db_prefix() . 'items i', 're.item_id = i.id', 'left');
        $this->db->where('re.revendedor_id', $revendedor_id);
        $this->db->where('re.quantidade <= re.quantidade_minima');
        $this->db->order_by('i.description', 'ASC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Obter histórico de movimentação
     * @param int $revendedor_id
     * @param int $item_id
     * @param int $limit
     * @return array
     */
    public function get_movimentacao($revendedor_id, $item_id = null, $limit = 50)
    {
        $this->db->select('rem.*, i.description as item_name, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'revendedor_estoque_movimentacao rem');
        $this->db->join(db_prefix() . 'items i', 'rem.item_id = i.id', 'left');
        $this->db->join(db_prefix() . 'staff s', 'rem.staff_id = s.staffid', 'left');
        $this->db->where('rem.revendedor_id', $revendedor_id);
        
        if ($item_id) {
            $this->db->where('rem.item_id', $item_id);
        }
        
        $this->db->order_by('rem.data_movimentacao', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result_array();
    }

    /**
     * Processar venda (baixar do estoque)
     * @param int $revendedor_id
     * @param array $items
     * @param int $invoice_id
     * @return boolean
     */
    public function processar_venda($revendedor_id, $items, $invoice_id)
    {
        foreach ($items as $item) {
            $resultado = $this->movimentar(
                $revendedor_id,
                $item['rel_id'],
                $item['qty'],
                'saida',
                'Venda - Fatura #' . $invoice_id,
                $invoice_id
            );
            
            if (isset($resultado['error'])) {
                return $resultado;
            }
        }
        
        return true;
    }

    /**
     * Reverter venda (devolver ao estoque)
     * @param int $invoice_id
     * @return boolean
     */
    public function reverter_venda($invoice_id)
    {
        // Obter movimentações da venda
        $this->db->where('invoice_id', $invoice_id);
        $this->db->where('tipo_movimentacao', 'saida');
        $movimentacoes = $this->db->get(db_prefix() . 'revendedor_estoque_movimentacao')->result_array();
        
        foreach ($movimentacoes as $mov) {
            $this->movimentar(
                $mov['revendedor_id'],
                $mov['item_id'],
                $mov['quantidade'],
                'entrada',
                'Estorno - Fatura #' . $invoice_id
            );
        }
        
        return true;
    }

    /**
     * Notificar sobre estoque baixo
     * @param int $revendedor_id
     * @param int $item_id
     * @param int $quantidade_atual
     */
    private function notificar_estoque_baixo($revendedor_id, $item_id, $quantidade_atual)
    {
        // Implementar notificação (email, sistema, etc.)
        // Por enquanto, apenas log
        $this->load->model('revendedores_model');
        $revendedor = $this->revendedores_model->get($revendedor_id);
        
        $this->db->where('id', $item_id);
        $item = $this->db->get(db_prefix() . 'items')->row();
        
        if ($revendedor && $item) {
            log_activity('Estoque baixo - Revendedor: ' . $revendedor->nome . ', Produto: ' . $item->description . ', Quantidade: ' . $quantidade_atual);
        }
    }

    /**
     * Sincronizar estoque com novos produtos
     * @param int $revendedor_id
     * @param int $item_id
     * @return boolean
     */
    public function sincronizar_novo_produto($revendedor_id, $item_id)
    {
        // Verificar se já existe
        $existe = $this->get($revendedor_id, $item_id);
        
        if ($existe) {
            return true;
        }
        
        // Obter dados do produto
        $this->db->where('id', $item_id);
        $item = $this->db->get(db_prefix() . 'items')->row();
        
        if (!$item) {
            return false;
        }
        
        // Criar entrada no estoque
        $estoque_data = [
            'revendedor_id' => $revendedor_id,
            'item_id' => $item_id,
            'quantidade' => 0,
            'quantidade_minima' => get_option('revendedores_quantidade_minima_padrao'),
            'preco_custo' => 0,
            'preco_venda' => $item->rate
        ];
        
        $this->db->insert(db_prefix() . 'revendedor_estoque', $estoque_data);
        
        return $this->db->affected_rows() > 0;
    }

    /**
     * Obter relatório de estoque
     * @param int $revendedor_id
     * @return array
     */
    public function get_relatorio_estoque($revendedor_id)
    {
        $this->db->select('
            re.*,
            i.description as item_name,
            i.unit,
            (re.quantidade * re.preco_custo) as valor_estoque_custo,
            (re.quantidade * re.preco_venda) as valor_estoque_venda
        ');
        $this->db->from(db_prefix() . 'revendedor_estoque re');
        $this->db->join(db_prefix() . 'items i', 're.item_id = i.id', 'left');
        $this->db->where('re.revendedor_id', $revendedor_id);
        $this->db->order_by('i.description', 'ASC');
        
        $estoque = $this->db->get()->result_array();
        
        $relatorio = [
            'produtos' => $estoque,
            'total_produtos' => count($estoque),
            'total_valor_custo' => 0,
            'total_valor_venda' => 0,
            'produtos_estoque_baixo' => 0
        ];
        
        foreach ($estoque as $item) {
            $relatorio['total_valor_custo'] += $item['valor_estoque_custo'];
            $relatorio['total_valor_venda'] += $item['valor_estoque_venda'];
            
            if ($item['quantidade'] <= $item['quantidade_minima']) {
                $relatorio['produtos_estoque_baixo']++;
            }
        }
        
        return $relatorio;
    }
}