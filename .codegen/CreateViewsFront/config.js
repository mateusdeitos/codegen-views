module.exports = {
	/**
	 * Array de regras para usar de fallback para tipagem
	 * Só irá chamar esses métodos caso o type do field seja 'any'
	 * Prefira sempre tipar os campos no próprio método da View
	 * Use isso somente em último caso, pois pode resultar em tipagens incorretas
	 */
	knownFieldsTypes: [
		{
			test: (fieldName) => fieldName.startsWith("id"),
			fieldType: "number | string",
		}
	]
}