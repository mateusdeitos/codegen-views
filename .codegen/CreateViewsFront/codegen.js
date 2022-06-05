const { CodeGen, Template } = require("tiny-codegen");
const { promisify } = require("util");
const { exec } = require("child_process");
const config = require("./config");

const script = promisify(exec);
const codegen = new CodeGen();

const getPhpEcho = async () => {
	try {
		const { stdout } = await script("php .codegen/CreateViewsFront/setup.php");
		return JSON.parse(stdout || "[]");
	} catch (error) {
		console.error(error);
		return [];
	}
}

const UppercaseFirstLetter = (string) => {
	return string.charAt(0).toUpperCase() + string.slice(1);
}

const parseParameterType = (name, type) => {
	if (type !== "any") return type;
	const fallback = config.knownFieldsTypes.find(f => f.test(name))?.fieldType || type;
	return fallback;
}

const buildParameter = (parameter) => {
	return [
		parameter.name,
		parameter.optional ? "?" : "",
		": ",
		parseParameterType(parameter.name, parameter.type),
	].join("");
}

const buildMethodParameters = (parameters) => {
	if (!Array.isArray(parameters)) {
		console.error("Parameters must be an array");
		return "";
	}

	return "\n\t" + parameters.map(buildParameter).join(",\n\t") + "\n"
}

const parseViewMethods = (viewPath, methods) => {
	if (!Array.isArray(methods)) {
		console.error("Methods must be an array");
		return "";
	}

	const clss = viewPath.split("/").join("\\\\").replace("View", "");

	return methods.map(method => {
		const parametersNames = method.parameters.map(parameter => parameter.name).join(", ");
		const parameters = buildMethodParameters(method.parameters);
		const returnType = method.returnType;
		return `
const ${method.name} = async function <TResponse = ${returnType}>(${parameters}) {
	return xajax.call({
		"type": 2,
		"func": {
			"clss": "${clss}",
			"metd": "${method.name}"
		}
	}, [${parametersNames}]) as TResponse;
}`;
	}).join("\n")
}

const getMethodNames = methods => {
	if (!Array.isArray(methods)) {
		console.error("Methods must be an array");
		return "";
	}

	return methods.map(method => "\t" + method.name).join(",\n");
}

const addTemplate = (view) => {
	const template = new Template(process.cwd(), '.codegen/CreateViewsFront/templates/');
	template.setCustomAnswersParser(() => {
		const parsedAnswers = [
			"path", `assets/js/views/${view.path}.ts`,
			"viewName", UppercaseFirstLetter(view.name),
			"methods", parseViewMethods(view.path, view.methods),
			"methodsNames", getMethodNames(view.methods),
		];

		return parsedAnswers;
	});

	codegen.addTemplate(template);
}

codegen.setConfig({
	setInitialTemplateAutomatically: false,
	onParseAllAnswers: async (answers) => {
		const result = await getPhpEcho();
		result.forEach(addTemplate)
	}
})


module.exports = codegen;
