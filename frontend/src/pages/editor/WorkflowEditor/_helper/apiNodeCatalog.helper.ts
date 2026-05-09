import type {
	INodeCategory,
	INodeSchema,
	INodeSchemaProperty,
	INodeType,
} from '@/types/nodeType.type';
import type {
	TFieldKind,
	TNodeCategory,
	TNodeDefinition,
	TNodeField,
	TNodePort,
	TPortType,
} from '../_types/node.type';

export type TNodeCategoryGroup = {
	id: string;
	label: string;
	color: string;
	order: number;
	nodes: TNodeDefinition[];
};

const HEX_TO_HUE: Record<string, string> = {
	'#F59E0B': 'amber',
	'#8B5CF6': 'violet',
	'#3B82F6': 'sky',
	'#10B981': 'emerald',
	'#EC4899': 'fuchsia',
	'#F97316': 'amber',
	'#6B7280': 'zinc',
	'#0EA5E9': 'sky',
	'#6366F1': 'indigo',
};

const CATEGORY_MAP: Record<string, TNodeCategory> = {
	triggers: 'input',
	ai: 'ai',
	'flow-control': 'flow-control',
	data: 'data',
	communication: 'communication',
	'http-apis': 'http-apis',
	utility: 'utility',
	storage: 'storage',
	debug: 'debug',
};

const normalizeHue = (color?: string) => {
	if (!color) return 'zinc';
	if (color.startsWith('#')) return HEX_TO_HUE[color.toUpperCase()] ?? 'zinc';
	return color;
};

const schemaTypeToPortType = (type?: string): TPortType => {
	if (type === 'string') return 'string';
	if (type === 'number' || type === 'integer') return 'number';
	if (type === 'boolean') return 'boolean';
	if (type === 'array') return 'list';
	if (type === 'object') return 'json';
	return 'any';
};

const schemaTypeToFieldKind = (property: INodeSchemaProperty): TFieldKind => {
	if (property.enum?.length) return 'select';
	if (property.type === 'boolean') return 'toggle';
	if (property.type === 'number' || property.type === 'integer') return 'number';
	if (property.type === 'object' || property.type === 'array') return 'code';
	return 'text';
};

const humanize = (value: string) =>
	value.replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

const schemaProperties = (schema?: INodeSchema) => schema?.properties ?? {};

const schemaToFields = (schema?: INodeSchema): TNodeField[] => {
	const required = new Set(schema?.required ?? []);

	return Object.entries(schemaProperties(schema)).map(([key, property]) => ({
		key,
		label: property.label ?? humanize(key),
		kind: schemaTypeToFieldKind(property),
		default: property.default,
		required: required.has(key),
		help: property.description,
		options: property.enum?.map((option) => ({ label: humanize(option), value: option })),
	}));
};

const schemaToPorts = (
	schema: INodeSchema | undefined,
	fallbackName: string,
	fallbackType: TPortType,
): TNodePort[] => {
	const properties = schemaProperties(schema);
	const entries = Object.entries(properties);

	if (!entries.length && schema) {
		return [{ id: fallbackName, name: fallbackName, type: fallbackType }];
	}

	return entries.map(([key, property]) => ({
		id: key,
		name: property.label ?? key,
		type: schemaTypeToPortType(property.type),
		required: schema?.required?.includes(key),
	}));
};

export const mapApiNodeToDefinition = (
	node: INodeType,
	categorySlug?: string,
	categoryColor?: string,
): TNodeDefinition => {
	const category = CATEGORY_MAP[categorySlug ?? ''] ?? 'integration';
	const configFields = schemaToFields(node.config_schema ?? node.schema);
	const inputPorts = schemaToPorts(node.input_schema, 'input', 'any');
	const outputPorts = schemaToPorts(node.output_schema, 'output', 'any');
	const fields = node.credential_type
		? [
				{
					key: 'credential_id',
					label: 'Credential',
					kind: 'credential' as const,
					required: true,
					help: `Select a ${node.credential_type} credential.`,
				},
				...configFields,
			]
		: configFields;

	return {
		key: node.type,
		category,
		label: node.name,
		description: node.description ?? '',
		icon: node.icon?.slice(0, 3).toUpperCase() ?? node.name.slice(0, 2).toUpperCase(),
		color: normalizeHue(node.color ?? categoryColor),
		inputs: node.node_kind === 'trigger' ? [] : inputPorts,
		outputs: outputPorts,
		fields,
		requiresCredential: Boolean(node.credential_type),
	};
};

export const mapApiCategoriesToGroups = (categories: INodeCategory[]): TNodeCategoryGroup[] =>
	categories
		.map((category) => ({
			id: category.slug,
			label: category.name,
			color: normalizeHue(category.color),
			order: category.sort_order,
			nodes: (category.nodes ?? []).map((node) =>
				mapApiNodeToDefinition(node, category.slug, category.color),
			),
		}))
		.sort((a, b) => a.order - b.order);
