'use client';

import { memo, useState } from 'react';
import { Handle, Position, NodeProps } from '@xyflow/react';
import { useCanvasStore, TableNodeData } from '@/lib/store';

const FIELD_TYPES = [
  'string',
  'text',
  'integer',
  'bigIncrements',
  'bigInteger',
  'boolean',
  'datetime',
  'decimal',
  'float',
  'json',
  'timestamps',
  'softDeletes',
];

function TableNodeComponent({ id, data }: NodeProps) {
  const nodeData = data as unknown as TableNodeData;
  const { updateNodeData, removeNode } = useCanvasStore();
  const [isEditing, setIsEditing] = useState(false);
  const [editName, setEditName] = useState(nodeData.label);

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setEditName(e.target.value);
  };

  const handleNameBlur = () => {
    updateNodeData(id, { label: editName });
    setIsEditing(false);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleNameBlur();
    }
  };

  const addField = () => {
    const newFields = [
      ...nodeData.fields,
      { name: 'new_field', type: 'string' },
    ];
    updateNodeData(id, { fields: newFields });
  };

  const updateField = (
    index: number,
    field: Partial<(typeof nodeData.fields)[0]>
  ) => {
    const newFields = nodeData.fields.map((f, i) =>
      i === index ? { ...f, ...field } : f
    );
    updateNodeData(id, { fields: newFields });
  };

  const removeField = (index: number) => {
    const newFields = nodeData.fields.filter((_, i) => i !== index);
    updateNodeData(id, { fields: newFields });
  };

  return (
    <div className="table-node">
      <Handle type="target" position={Position.Top} />

      <div className="table-node-header">
        {isEditing ? (
          <input
            type="text"
            value={editName}
            onChange={handleNameChange}
            onBlur={handleNameBlur}
            onKeyDown={handleKeyDown}
            autoFocus
          />
        ) : (
          <span onClick={() => setIsEditing(true)}>
            {nodeData.label}
          </span>
        )}
        <button onClick={() => removeNode(id)}>×</button>
      </div>

      <div className="table-node-body">
        {nodeData.fields.map((field, index) => (
          <div key={index} className="field-row">
            <input
              type="text"
              value={field.name}
              onChange={(e) => updateField(index, { name: e.target.value })}
              placeholder="name"
            />
            <select
              value={field.type}
              onChange={(e) => updateField(index, { type: e.target.value })}
            >
              {FIELD_TYPES.map((type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ))}
            </select>
            <button onClick={() => removeField(index)}>×</button>
          </div>
        ))}

        <button className="add-field-btn" onClick={addField}>
          + Add Field
        </button>
      </div>

      <Handle type="source" position={Position.Bottom} />
    </div>
  );
}

export const TableNode = memo(TableNodeComponent);
