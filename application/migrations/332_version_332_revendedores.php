<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_332_revendedores extends CI_Migration
{
    public function up(): void
    {
        // Tabela de revendedores
        $this->db->query('CREATE TABLE `' . db_prefix() . 'revendedores` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nome` varchar(191) NOT NULL,
            `email` varchar(100) NOT NULL,
            `telefone` varchar(50) DEFAULT NULL,
            `cpf_cnpj` varchar(20) DEFAULT NULL,
            `endereco` text DEFAULT NULL,
            `cidade` varchar(100) DEFAULT NULL,
            `estado` varchar(50) DEFAULT NULL,
            `cep` varchar(20) DEFAULT NULL,
            `taxa_comissao` decimal(15,2) DEFAULT 0.00,
            `meta_mensal` decimal(15,2) DEFAULT 0.00,
            `ativo` tinyint(1) DEFAULT 1,
            `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
            `staff_id` int(11) DEFAULT NULL,
            `observacoes` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            KEY `staff_id` (`staff_id`),
            CONSTRAINT `fk_revendedor_staff` FOREIGN KEY (`staff_id`) REFERENCES `' . db_prefix() . 'staff` (`staffid`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');

        // Tabela de estoque por revendedor
        $this->db->query('CREATE TABLE `' . db_prefix() . 'revendedor_estoque` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `revendedor_id` int(11) NOT NULL,
            `item_id` int(11) NOT NULL,
            `quantidade` int(11) DEFAULT 0,
            `quantidade_minima` int(11) DEFAULT 0,
            `preco_custo` decimal(15,2) DEFAULT 0.00,
            `preco_venda` decimal(15,2) DEFAULT 0.00,
            `data_atualizacao` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `revendedor_item` (`revendedor_id`, `item_id`),
            KEY `revendedor_id` (`revendedor_id`),
            KEY `item_id` (`item_id`),
            CONSTRAINT `fk_estoque_revendedor` FOREIGN KEY (`revendedor_id`) REFERENCES `' . db_prefix() . 'revendedores` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_estoque_item` FOREIGN KEY (`item_id`) REFERENCES `' . db_prefix() . 'items` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');

        // Tabela de solicitações de reposição
        $this->db->query('CREATE TABLE `' . db_prefix() . 'revendedor_solicitacoes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `revendedor_id` int(11) NOT NULL,
            `item_id` int(11) NOT NULL,
            `quantidade_solicitada` int(11) NOT NULL,
            `quantidade_aprovada` int(11) DEFAULT NULL,
            `status` enum("pendente","aprovada","rejeitada","enviada","recebida") DEFAULT "pendente",
            `observacoes` text DEFAULT NULL,
            `data_solicitacao` datetime DEFAULT CURRENT_TIMESTAMP,
            `data_aprovacao` datetime DEFAULT NULL,
            `aprovado_por` int(11) DEFAULT NULL,
            `data_envio` datetime DEFAULT NULL,
            `data_recebimento` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `revendedor_id` (`revendedor_id`),
            KEY `item_id` (`item_id`),
            KEY `aprovado_por` (`aprovado_por`),
            CONSTRAINT `fk_solicitacao_revendedor` FOREIGN KEY (`revendedor_id`) REFERENCES `' . db_prefix() . 'revendedores` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_solicitacao_item` FOREIGN KEY (`item_id`) REFERENCES `' . db_prefix() . 'items` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_solicitacao_aprovador` FOREIGN KEY (`aprovado_por`) REFERENCES `' . db_prefix() . 'staff` (`staffid`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');

        // Tabela de comissões
        $this->db->query('CREATE TABLE `' . db_prefix() . 'revendedor_comissoes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `revendedor_id` int(11) NOT NULL,
            `invoice_id` int(11) NOT NULL,
            `valor_venda` decimal(15,2) NOT NULL,
            `percentual_comissao` decimal(5,2) NOT NULL,
            `valor_comissao` decimal(15,2) NOT NULL,
            `status` enum("pendente","paga","cancelada") DEFAULT "pendente",
            `data_venda` datetime NOT NULL,
            `data_pagamento` datetime DEFAULT NULL,
            `observacoes` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `revendedor_id` (`revendedor_id`),
            KEY `invoice_id` (`invoice_id`),
            CONSTRAINT `fk_comissao_revendedor` FOREIGN KEY (`revendedor_id`) REFERENCES `' . db_prefix() . 'revendedores` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_comissao_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `' . db_prefix() . 'invoices` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');

        // Adicionar campo revendedor_id na tabela de invoices
        $this->db->query('ALTER TABLE `' . db_prefix() . 'invoices` ADD COLUMN `revendedor_id` int(11) DEFAULT NULL AFTER `sale_agent`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'invoices` ADD KEY `revendedor_id` (`revendedor_id`)');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'invoices` ADD CONSTRAINT `fk_invoice_revendedor` FOREIGN KEY (`revendedor_id`) REFERENCES `' . db_prefix() . 'revendedores` (`id`) ON DELETE SET NULL');

        // Adicionar campo revendedor_id na tabela de clients para vincular clientes aos revendedores
        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` ADD COLUMN `revendedor_id` int(11) DEFAULT NULL AFTER `userid`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` ADD KEY `revendedor_id` (`revendedor_id`)');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` ADD CONSTRAINT `fk_client_revendedor` FOREIGN KEY (`revendedor_id`) REFERENCES `' . db_prefix() . 'revendedores` (`id`) ON DELETE SET NULL');

        // Tabela de movimentação de estoque
        $this->db->query('CREATE TABLE `' . db_prefix() . 'revendedor_estoque_movimentacao` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `revendedor_id` int(11) NOT NULL,
            `item_id` int(11) NOT NULL,
            `tipo_movimentacao` enum("entrada","saida","ajuste") NOT NULL,
            `quantidade` int(11) NOT NULL,
            `quantidade_anterior` int(11) NOT NULL,
            `quantidade_atual` int(11) NOT NULL,
            `motivo` varchar(255) DEFAULT NULL,
            `invoice_id` int(11) DEFAULT NULL,
            `data_movimentacao` datetime DEFAULT CURRENT_TIMESTAMP,
            `staff_id` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `revendedor_id` (`revendedor_id`),
            KEY `item_id` (`item_id`),
            KEY `invoice_id` (`invoice_id`),
            KEY `staff_id` (`staff_id`),
            CONSTRAINT `fk_movimentacao_revendedor` FOREIGN KEY (`revendedor_id`) REFERENCES `' . db_prefix() . 'revendedores` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_movimentacao_item` FOREIGN KEY (`item_id`) REFERENCES `' . db_prefix() . 'items` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_movimentacao_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `' . db_prefix() . 'invoices` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_movimentacao_staff` FOREIGN KEY (`staff_id`) REFERENCES `' . db_prefix() . 'staff` (`staffid`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');

        // Adicionar opções de configuração
        add_option('revendedores_ativo', '1');
        add_option('revendedores_comissao_padrao', '10.00');
        add_option('revendedores_meta_padrao', '5000.00');
        add_option('revendedores_notificar_estoque_baixo', '1');
        add_option('revendedores_quantidade_minima_padrao', '5');
    }

    public function down(): void
    {
        // Remover constraints e campos adicionados
        $this->db->query('ALTER TABLE `' . db_prefix() . 'invoices` DROP FOREIGN KEY `fk_invoice_revendedor`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'invoices` DROP KEY `revendedor_id`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'invoices` DROP COLUMN `revendedor_id`');

        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` DROP FOREIGN KEY `fk_client_revendedor`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` DROP KEY `revendedor_id`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` DROP COLUMN `revendedor_id`');

        // Remover tabelas
        $this->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'revendedor_estoque_movimentacao`');
        $this->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'revendedor_comissoes`');
        $this->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'revendedor_solicitacoes`');
        $this->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'revendedor_estoque`');
        $this->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'revendedores`');

        // Remover opções
        delete_option('revendedores_ativo');
        delete_option('revendedores_comissao_padrao');
        delete_option('revendedores_meta_padrao');
        delete_option('revendedores_notificar_estoque_baixo');
        delete_option('revendedores_quantidade_minima_padrao');
    }
}