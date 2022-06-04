
const obterSelectEcommerces = async function <TResponse = unknown>(opcoes: Record<string, boolean>, params?: Record<string, number>, plataformas?: number[] | string[], retornarDados?: boolean) {
	return xajax.call({
		"type": 2,
		"func": {
			"clss": "Ecommerce",
			"metd": "obterSelectEcommerces"
		}
	}, [opcoes, params, plataformas, retornarDados]) as TResponse;
}

export const EcommerceView = {
	obterSelectEcommerces
}