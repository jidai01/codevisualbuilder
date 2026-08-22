import { create } from 'zustand';
import {
  Node,
  Edge,
  OnNodesChange,
  OnEdgesChange,
  applyNodeChanges,
  applyEdgeChanges,
  addEdge,
  Connection,
} from '@xyflow/react';
import { v4 as uuidv4 } from 'uuid';

export interface TableField {
  name: string;
  type: string;
  nullable?: boolean;
  default?: string;
  unique?: boolean;
  index?: boolean;
  unsigned?: boolean;
}

export interface TableRelation {
  type: 'belongsTo' | 'hasMany' | 'hasOne' | 'belongsToMany';
  target: string;
  foreignKey?: string;
  pivotTable?: string;
}

export interface TableNodeData {
  label: string;
  fields: TableField[];
  relations: TableRelation[];
  [key: string]: unknown;
}

export interface BlueprintProject {
  project: string;
  entities: {
    name: string;
    fields: TableField[];
    relations: TableRelation[];
  }[];
}

export interface AppState {
  view: 'canvas' | 'ide';
  workspaceUuid: string | null;
  setView: (view: 'canvas' | 'ide') => void;
  setWorkspaceUuid: (uuid: string) => void;
}

interface CanvasState extends AppState {
  nodes: Node<TableNodeData>[];
  edges: Edge[];
  projectName: string;
  onNodesChange: OnNodesChange;
  onEdgesChange: OnEdgesChange;
  onConnect: (connection: Connection) => void;
  addNode: (position: { x: number; y: number }) => void;
  updateNodeData: (nodeId: string, data: Partial<TableNodeData>) => void;
  removeNode: (nodeId: string) => void;
  setProjectName: (name: string) => void;
  generateBlueprint: () => BlueprintProject;
  hydrateFromBlueprint: (uuid: string, blueprint: BlueprintProject) => void;
  resetCanvas: () => void;
}

function buildNodesFromBlueprint(blueprint: BlueprintProject): Node<TableNodeData>[] {
  const nodeCount = blueprint.entities.length;
  const cols = Math.ceil(Math.sqrt(nodeCount));
  const spacingX = 350;
  const spacingY = 300;

  return blueprint.entities.map((entity, i) => {
    const col = i % cols;
    const row = Math.floor(i / cols);

    return {
      id: uuidv4(),
      type: 'tableNode',
      position: { x: col * spacingX + 50, y: row * spacingY + 50 },
      data: {
        label: entity.name,
        fields: entity.fields,
        relations: entity.relations,
      },
    };
  });
}

function buildEdgesFromNodes(nodes: Node<TableNodeData>[]): Edge[] {
  const edges: Edge[] = [];

  nodes.forEach((node) => {
    const data = node.data as unknown as TableNodeData;
    data.relations.forEach((rel) => {
      if (rel.type === 'belongsTo') {
        const targetNode = nodes.find(
          (n) => (n.data as unknown as TableNodeData).label === rel.target
        );
        if (targetNode) {
          edges.push({
            id: uuidv4(),
            source: targetNode.id,
            target: node.id,
            type: 'smoothstep',
          });
        }
      }
    });
  });

  return edges;
}

export const useCanvasStore = create<CanvasState>((set, get) => ({
  nodes: [],
  edges: [],
  projectName: 'MyProject',
  view: 'canvas',
  workspaceUuid: null,

  setView: (view) => set({ view }),
  setWorkspaceUuid: (uuid) => set({ workspaceUuid: uuid }),

  onNodesChange: (changes) => {
    set({ nodes: applyNodeChanges(changes, get().nodes) as Node<TableNodeData>[] });
  },

  onEdgesChange: (changes) => {
    set({ edges: applyEdgeChanges(changes, get().edges) });
  },

  onConnect: (connection) => {
    set({ edges: addEdge(connection, get().edges) });
  },

  addNode: (position) => {
    const id = uuidv4();
    const newNode: Node<TableNodeData> = {
      id,
      type: 'tableNode',
      position,
      data: {
        label: 'NewTable',
        fields: [
          { name: 'id', type: 'bigIncrements' },
          { name: 'name', type: 'string' },
          { name: 'timestamps', type: 'timestamps' },
        ],
        relations: [],
      },
    };
    set({ nodes: [...get().nodes, newNode] });
  },

  updateNodeData: (nodeId, data) => {
    set({
      nodes: get().nodes.map((node) =>
        node.id === nodeId
          ? { ...node, data: { ...node.data, ...data } }
          : node
      ),
    });
  },

  removeNode: (nodeId) => {
    set({
      nodes: get().nodes.filter((node) => node.id !== nodeId),
      edges: get().edges.filter(
        (edge) => edge.source !== nodeId && edge.target !== nodeId
      ),
    });
  },

  setProjectName: (name) => {
    set({ projectName: name });
  },

  generateBlueprint: () => {
    const { nodes, edges, projectName } = get();

    const entities = nodes.map((node) => {
      const sourceRelations = edges
        .filter((edge) => edge.source === node.id)
        .map((edge) => {
          const targetNode = nodes.find((n) => n.id === edge.target);
          return {
            type: 'hasMany' as const,
            target: targetNode?.data.label || 'Unknown',
            foreignKey: edge.data?.foreignKey as string | undefined,
          };
        });

      const targetRelations = edges
        .filter((edge) => edge.target === node.id)
        .map((edge) => {
          const sourceNode = nodes.find((n) => n.id === edge.source);
          return {
            type: 'belongsTo' as const,
            target: sourceNode?.data.label || 'Unknown',
            foreignKey: edge.data?.foreignKey as string | undefined,
          };
        });

      return {
        name: node.data.label,
        fields: node.data.fields,
        relations: [...sourceRelations, ...targetRelations],
      };
    });

    return {
      project: projectName,
      entities,
    };
  },

  hydrateFromBlueprint: (uuid, blueprint) => {
    const nodes = buildNodesFromBlueprint(blueprint);
    const edges = buildEdgesFromNodes(nodes);

    set({
      workspaceUuid: uuid,
      projectName: blueprint.project,
      nodes,
      edges,
      view: 'canvas',
    });
  },

  resetCanvas: () => {
    set({
      nodes: [],
      edges: [],
      projectName: 'MyProject',
      view: 'canvas',
      workspaceUuid: null,
    });
  },
}));
