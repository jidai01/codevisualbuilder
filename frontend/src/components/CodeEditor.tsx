'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import Editor from '@monaco-editor/react';

interface CodeEditorProps {
  uuid: string;
  filePath: string | null;
  onFileSaved?: (path: string) => void;
}

function getLanguage(filename: string): string {
  const ext = filename.split('.').pop()?.toLowerCase() || '';
  switch (ext) {
    case 'php': return 'php';
    case 'ts':
    case 'tsx': return 'typescript';
    case 'js':
    case 'jsx': return 'javascript';
    case 'json': return 'json';
    case 'md': return 'markdown';
    case 'css': return 'css';
    case 'html':
    case 'blade.php': return 'html';
    case 'stub': return 'php';
    case 'env':
    case 'example': return 'ini';
    case 'xml':
    case 'yaml':
    case 'yml': return 'yaml';
    default: return 'plaintext';
  }
}

export default function CodeEditor({ uuid, filePath, onFileSaved }: CodeEditorProps) {
  const [content, setContent] = useState<string>('');
  const [originalContent, setOriginalContent] = useState<string>('');
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [modified, setModified] = useState(false);
  const editorRef = useRef<any>(null);

  useEffect(() => {
    if (filePath) {
      loadFile(filePath);
    }
  }, [filePath]);

  useEffect(() => {
    setModified(content !== originalContent && originalContent !== '');
  }, [content, originalContent]);

  const loadFile = async (path: string) => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`http://localhost:8000/api/workspace/${uuid}/file?path=${encodeURIComponent(path)}`);
      const data = await res.json();

      if (data.error) {
        setError(data.error);
        setContent('');
        setOriginalContent('');
      } else if (data.binary) {
        setError('Binary file - cannot be edited');
        setContent('');
        setOriginalContent('');
      } else {
        setContent(data.content || '');
        setOriginalContent(data.content || '');
      }
    } catch (err) {
      setError('Failed to load file');
      setContent('');
      setOriginalContent('');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = useCallback(async () => {
    if (!filePath || !modified) return;

    setSaving(true);
    try {
      const res = await fetch(`http://localhost:8000/api/workspace/${uuid}/file`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ path: filePath, content }),
      });

      const data = await res.json();

      if (data.success) {
        setOriginalContent(content);
        setModified(false);
        onFileSaved?.(filePath);
      } else {
        setError(data.error || 'Failed to save');
      }
    } catch (err) {
      setError('Failed to save file');
    } finally {
      setSaving(false);
    }
  }, [filePath, content, modified, uuid, onFileSaved]);

  const handleEditorChange = (value: string | undefined) => {
    setContent(value || '');
  };

  const handleEditorMount = (editor: any) => {
    editorRef.current = editor;

    editor.addCommand(2048 | 49, () => {
      handleSave();
    });
  };

  if (!filePath) {
    return (
      <div className="h-full flex items-center justify-center bg-[#1e1e1e] text-gray-500">
        <div className="text-center">
          <div className="text-6xl mb-4">{'</>'}</div>
          <div className="text-lg">Select a file to edit</div>
          <div className="text-sm mt-2 text-gray-600">Ctrl+S to save</div>
        </div>
      </div>
    );
  }

  const filename = filePath.split('/').pop() || filePath;

  return (
    <div className="h-full flex flex-col bg-[#1e1e1e]">
      <div className="flex items-center justify-between px-4 py-2 bg-[#252526] border-b border-[#333]">
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-2">
            <span className="text-sm text-gray-300 font-mono">{filePath}</span>
            {modified && <span className="text-yellow-400 text-xs">●</span>}
          </div>
        </div>
        <div className="flex items-center gap-2">
          {error && (
            <span className="text-red-400 text-xs mr-2">{error}</span>
          )}
          <button
            onClick={handleSave}
            disabled={!modified || saving}
            className={`px-3 py-1 text-sm rounded font-medium transition-colors ${
              modified
                ? 'bg-blue-600 hover:bg-blue-700 text-white'
                : 'bg-[#333] text-gray-600 cursor-not-allowed'
            }`}
          >
            {saving ? 'Saving...' : 'Save'}
          </button>
        </div>
      </div>

      <div className="flex-1 min-h-0">
        {loading ? (
          <div className="h-full flex items-center justify-center text-gray-500">
            Loading...
          </div>
        ) : error && error !== 'Binary file - cannot be edited' ? (
          <div className="h-full flex items-center justify-center text-red-400">
            {error}
          </div>
        ) : (
          <Editor
            height="100%"
            language={getLanguage(filename)}
            value={content}
            onChange={handleEditorChange}
            onMount={handleEditorMount}
            theme="vs-dark"
            options={{
              fontSize: 14,
              fontFamily: "'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace",
              minimap: { enabled: true },
              scrollBeyondLastLine: false,
              wordWrap: 'on',
              automaticLayout: true,
              tabSize: 4,
              renderWhitespace: 'selection',
              bracketPairColorization: { enabled: true },
              cursorBlinking: 'smooth',
              smoothScrolling: true,
              padding: { top: 10 },
            }}
          />
        )}
      </div>
    </div>
  );
}
