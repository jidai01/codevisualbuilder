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
        className="tree-item"
        style={{
          paddingLeft: `${12 + level * 16}px`,
          backgroundColor: isActive ? 'rgba(59, 130, 246, 0.3)' : 'transparent',
          borderLeft: isActive ? '2px solid #3b82f6' : '2px solid transparent',
        }}
      >
        {node.type === 'directory' ? (
          <span className="tree-arrow">
            {expanded ? '▼' : '▶'}
          </span>
        ) : (
          <span className="tree-arrow-placeholder" />
        )}

        {node.type === 'directory' ? (
          <span className="tree-folder">
            {expanded ? '📂' : '📁'}
          </span>
        ) : (
          <span
            className="tree-file-icon"
            style={{ backgroundColor: getFileColor(node.name) + '22', color: getFileColor(node.name) }}
          >
            {getFileIcon(node.name)}
          </span>
        )}

        <span className={`tree-label ${node.type === 'directory' ? 'directory' : 'file'}`}>
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
      const res = await fetch(`/api/workspace/${uuid}/tree`);
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
    <div className="file-explorer">
      <div className="file-explorer-header">
        <div className="file-explorer-title">Explorer</div>
        <input
          type="text"
          placeholder="Search files..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="file-explorer-search"
        />
      </div>

      <div className="file-explorer-tree">
        {loading ? (
          <div className="file-explorer-empty">Loading...</div>
        ) : displayTree.length === 0 ? (
          <div className="file-explorer-empty">No files found</div>
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

      <div className="file-explorer-footer">
        {tree.length} root items
      </div>
    </div>
  );
}
