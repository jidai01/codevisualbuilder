'use client';

import { useState, useEffect } from 'react';

interface FileNode {
  name: string;
  path: string;
  type: 'file' | 'directory';
  children?: FileNode[];
  size?: number;
  modified?: number;
}

interface FileExplorerProps {
  uuid: string;
  onFileSelect: (path: string) => void;
  activeFile: string | null;
}

const FILE_ICONS: Record<string, string> = {
  php: '<?',
  ts: 'TS',
  tsx: 'TX',
  js: 'JS',
  json: '{}',
  md: 'M',
  css: '#',
  html: '<>',
  stub: 'S',
  sqlite: 'DB',
  env: 'E',
};

function getFileIcon(name: string): string {
  const ext = name.split('.').pop()?.toLowerCase() || '';
  return FILE_ICONS[ext] || 'F';
}

function getFileColor(name: string): string {
  const ext = name.split('.').pop()?.toLowerCase() || '';
  switch (ext) {
    case 'php': return '#7b7fff';
    case 'ts':
    case 'tsx': return '#3178c6';
    case 'js': return '#f7df1e';
    case 'json': return '#a8b9cc';
    case 'stub': return '#ff6b6b';
    case 'sqlite': return '#003b57';
    case 'env': return '#ecd53f';
    case 'css': return '#264de4';
    default: return '#8b8b8b';
  }
}

function TreeItem({
  node,
  uuid,
  onFileSelect,
  activeFile,
  level = 0,
}: {
  node: FileNode;
  uuid: string;
  onFileSelect: (path: string) => void;
  activeFile: string | null;
  level?: number;
}) {
  const [expanded, setExpanded] = useState(level < 1);

  const handleClick = () => {
    if (node.type === 'directory') {
      setExpanded(!expanded);
    } else {
      onFileSelect(node.path);
    }
  };

  const isActive = activeFile === node.path;

  return (
    <div>
      <div
        onClick={handleClick}
        style={{
          paddingLeft: `${12 + level * 16}px`,
          backgroundColor: isActive ? 'rgba(59, 130, 246, 0.3)' : 'transparent',
          borderLeft: isActive ? '2px solid #3b82f6' : '2px solid transparent',
        }}
        className="flex items-center gap-2 py-1 px-2 cursor-pointer hover:bg-white/5 text-sm select-none"
      >
        {node.type === 'directory' ? (
          <span className="text-gray-500 w-4 text-center text-xs">
            {expanded ? '▼' : '▶'}
          </span>
        ) : (
          <span className="w-4" />
        )}

        {node.type === 'directory' ? (
          <span className="text-yellow-500 text-xs">
            {expanded ? '📂' : '📁'}
          </span>
        ) : (
          <span
            className="text-xs font-bold w-5 h-5 flex items-center justify-center rounded"
            style={{ backgroundColor: getFileColor(node.name) + '22', color: getFileColor(node.name) }}
          >
            {getFileIcon(node.name)}
          </span>
        )}

        <span className={`truncate ${node.type === 'directory' ? 'text-gray-300 font-medium' : 'text-gray-400'}`}>
          {node.name}
        </span>
      </div>

      {node.type === 'directory' && expanded && node.children && (
        <div>
          {node.children.map((child) => (
            <TreeItem
              key={child.path}
              node={child}
              uuid={uuid}
              onFileSelect={onFileSelect}
              activeFile={activeFile}
              level={level + 1}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export default function FileExplorer({ uuid, onFileSelect, activeFile }: FileExplorerProps) {
  const [tree, setTree] = useState<FileNode[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  useEffect(() => {
    fetchTree();
  }, [uuid]);

  const fetchTree = async () => {
    try {
      const res = await fetch(`http://localhost:8000/api/workspace/${uuid}/tree`);
      const data = await res.json();
      setTree(data);
    } catch (err) {
      console.error('Failed to fetch tree:', err);
    } finally {
      setLoading(false);
    }
  };

  const filterTree = (nodes: FileNode[], query: string): FileNode[] => {
    if (!query) return nodes;

    return nodes
      .map((node) => {
        if (node.type === 'directory' && node.children) {
          const filtered = filterTree(node.children, query);
          if (filtered.length > 0) {
            return { ...node, children: filtered };
          }
        }
        if (node.name.toLowerCase().includes(query.toLowerCase())) {
          return node;
        }
        return null;
      })
      .filter(Boolean) as FileNode[];
  };

  const displayTree = search ? filterTree(tree, search) : tree;

  return (
    <div className="h-full flex flex-col bg-[#1e1e1e]">
      <div className="px-3 py-2 border-b border-[#333]">
        <div className="text-xs text-gray-500 uppercase tracking-wider mb-2 font-semibold">
          Explorer
        </div>
        <input
          type="text"
          placeholder="Search files..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full bg-[#2d2d2d] text-gray-300 text-sm px-2 py-1 rounded border border-[#444] focus:border-blue-500 focus:outline-none"
        />
      </div>

      <div className="flex-1 overflow-y-auto py-1">
        {loading ? (
          <div className="text-gray-500 text-sm p-4 text-center">Loading...</div>
        ) : displayTree.length === 0 ? (
          <div className="text-gray-500 text-sm p-4 text-center">No files found</div>
        ) : (
          displayTree.map((node) => (
            <TreeItem
              key={node.path}
              node={node}
              uuid={uuid}
              onFileSelect={onFileSelect}
              activeFile={activeFile}
            />
          ))
        )}
      </div>

      <div className="px-3 py-2 border-t border-[#333] text-xs text-gray-600">
        {tree.length} root items
      </div>
    </div>
  );
}
