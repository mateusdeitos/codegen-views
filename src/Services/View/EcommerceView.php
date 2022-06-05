<?php

namespace Services\View;

class EcommerceView  {

	public function _montarSelectEcommerces($plataformasLiberadas, $params = []) {
	}

	/**
	 * Função para obter o html de um select ou array com opções
	 * dos e-commerces do usuário
	 * @param  array<string,bool> $opcoes
	 * @param  array<string,int>  $params
	 * @param  int[] $plataformas
	 * @return array|string[]
	 *         String contendo o select inteiro ou somente as opções
	 */
	public function obterSelectEcommerces($opcoes, $params = [], $plataformas = [], $retornarDados = false) {
	}

	// public function gerarLinksWebhooks($idEcommerce, $webhooks) {
	// }

	// public function obterDadosPopupWebhookTerceiros($plataforma, $idEcommerce) {
	// }

	// public function _obterPlataforma($id) {
	// }

	// public function _obterComAdicionais($id) {
	// }

	// public function obterOptionsTipoAnuncio($plataforma) {
	// }

	// public function reenviarUrlWebhookEcommerce($idEcommerce) {
	// }
}