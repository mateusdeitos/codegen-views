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

const UppercaseFirstLetter = (string) => {
	return string.charAt(0).toUpperCase() + string.slice(1);
}

const parseParameterType = (parameter) => {
	let type = "any";
	if (!!parameter?.type) {
		type = parameter.type;
	} else if (!!parameter?.docType) {
		type = parameter.docType;
	}

	const isArray = type => type.includes("[]") && type.indexOf("[") > 0;
	const isOptional = parameter.optional === true;
	const isTuple = type => type.includes("<") && type.includes(">") && type.includes(",") && type.indexOf("<") < type.indexOf(">");

	const parseType = ({ type, key = "", value = "" }) => {
		switch (type) {
			case "array":
			case "object":
				if (!!key && !!value) return `Record<${parseType({ type: key })}, ${parseType({ type: value })}>`;
				if (!!value) return `${parseType({ type: value })}[]`;
				return "Record<string, any>";
			case "string":
				return "string";
			case "int":
			case "float":
				return "number";
			case "bool":
			case "boolean":
				return "boolean";

			default:
				return "any";
		}
	}

	const parseUnionType = (type) => {
		const types = type.split("|");
		return types.map(t => {
			if (isArray(t)) {
				return parseType({
					type: "array",
					value: t.replace("[]", "").trim()
				});
			}

			if (isTuple(t)) {
				const key = t.split("<")[1].split(",")[0].trim();
				const value = t.split(">")[0].split(",")[1].trim();
				const baseType = t.split("<")[0].trim();
				return parseType({
					type: baseType,
					key,
					value
				});
			}

			return parseType({ type: t })
		}).join(" | ");
	}

	let parsedType = parseUnionType(type);

	return [
		isOptional ? "?" : "",
		": ",
		parsedType,
	].join("");
}

const buildParameter = (parameter) => {
	const type = parseParameterType(parameter);
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
	return UppercaseFirstLetter(name);
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
		return `
const ${method.name} = async function <TResponse = unknown>(${parameters}) {
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
