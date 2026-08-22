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
      <div className="code-editor-empty">
        <div className="code-editor-empty-icon">{'</>'}</div>
        <div className="code-editor-empty-text">Select a file to edit</div>
        <div className="code-editor-empty-hint">Ctrl+S to save</div>
      </div>
    );
  }

  const filename = filePath.split('/').pop() || filePath;

  return (
    <div className="code-editor">
      <div className="code-editor-toolbar">
        <div className="code-editor-toolbar-left">
          <span className="code-editor-filename">{filePath}</span>
          {modified && <span className="code-editor-modified">●</span>}
        </div>
        <div className="code-editor-toolbar-right">
          {error && (
            <span className="code-editor-error">{error}</span>
          )}
          <button
            onClick={handleSave}
            disabled={!modified || saving}
            className={`code-editor-save-btn ${modified ? 'modified' : ''}`}
          >
            {saving ? 'Saving...' : 'Save'}
          </button>
        </div>
      </div>

      <div className="code-editor-content">
        {loading ? (
          <div className="code-editor-loading">Loading...</div>
        ) : error && error !== 'Binary file - cannot be edited' ? (
          <div className="code-editor-loading error">{error}</div>
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
