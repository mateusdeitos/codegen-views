const { CodeGen, Template } = require("tiny-codegen");
const { promisify } = require("util");
const { exec } = require("child_process");

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

const parseParameterType = (type, isOptional) => {
	if (isOptional) return "?: any";
	return ": any";
}

const buildParameter = (parameter) => {
	const type = parseParameterType(parameter.type, parameter.optional);
	return `${parameter.name}${type}`;
}

const buildMethodParameters = (parameters) => {
	if (!Array.isArray(parameters)) {
		console.error("Parameters must be an array");
		return "";
	}

	return parameters.map(parameter => {
		return buildParameter(parameter);
	}).join(", ")
}

const parseViewName = name => {
	return name[0].toLowerCase() + name.slice(1);
}

const parseViewMethods = (viewPath, methods) => {
	if (!Array.isArray(methods)) {
		console.error("Methods must be an array");
		return "";
	}

	const clss = viewPath.split("/").join("\\\\");

	return methods.map(method => {
		const parameters = buildMethodParameters(method.parameters);
		return `
const ${method.name} = async function <TResponse = unknown>(${parameters}) {
	return xajax.call({
		"type": 2,
		"func": {
			"clss": "${clss}",
			"metd": "${method.name}"
		}
	}, arguments) as TResponse;
}`;
	}).join("\n")
}

const getMethodNames = methods => {
	if (!Array.isArray(methods)) {
		console.error("Methods must be an array");
		return "";
	}

	return methods.map(method => "\t\t" + method.name).join(",\n");
}

const addTemplate = (view) => {
	const template = new Template(process.cwd(), '.codegen/CreateViewsFront/templates/');
	template.setCustomAnswersParser(() => {
		const parsedAnswers = [
			"path", `assets/js/views/${view.path}.ts`,
			"viewName", parseViewName(view.name),
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
		result.forEach(view => {
			addTemplate(view);
		})
	}
})


module.exports = codegen;
