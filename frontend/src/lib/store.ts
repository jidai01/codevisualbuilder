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

interface CanvasState {
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
}

export const useCanvasStore = create<CanvasState>((set, get) => ({
  nodes: [],
  edges: [],
  projectName: 'MyProject',

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
}));
