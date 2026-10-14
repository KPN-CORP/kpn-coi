import { Node } from '@tiptap/core'
import type { Node as ProseMirrorNode } from '@tiptap/pm/model'
import type { EditorView } from '@tiptap/pm/view'
import { Table, TableCell, TableHeader, TableRow, TableView } from '@tiptap/extension-table'

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        formTab: {
            insertFormTab: () => ReturnType
        }
    }
}

/**
 * A fixed-width gap (1.27 cm, Word's default tab stop) inserted by the Tab
 * key. Stored as `<span data-tab="1"></span>` and sized by CSS in the editor,
 * on screen and in the PDF -- a literal tab character would collapse to a
 * single space in both the browser and dompdf.
 *
 * Tab keeps its usual meaning inside lists (indent the item) and tables (next
 * cell); only plain text gets a gap.
 */
export const TabNode = Node.create({
    name: 'formTab',

    group: 'inline',

    inline: true,

    atom: true,

    selectable: false,

    // Run before the list and table keymaps so this handler decides first,
    // then hands Tab back to them where it means something else.
    priority: 1000,

    parseHTML() {
        return [{ tag: 'span[data-tab]' }]
    },

    renderHTML() {
        return ['span', { 'data-tab': '1' }]
    },

    renderText() {
        return '\t'
    },

    addCommands() {
        return {
            insertFormTab:
                () =>
                ({ commands }) =>
                    commands.insertContent({ type: this.name }),
        }
    },

    addKeyboardShortcuts() {
        return {
            Tab: () => {
                if (this.editor.isActive('listItem') || this.editor.isActive('table')) {
                    return false
                }

                return this.editor.commands.insertFormTab()
            },
        }
    },
})

/**
 * Tiptap's resizable table view only applies the column widths it manages;
 * this one also mirrors the `borderless` flag onto the <table> so the editor
 * shows the table the way it will print.
 */
class FormTableView extends TableView {
    constructor(node: ProseMirrorNode, cellMinWidth: number, view?: EditorView, HTMLAttributes?: Record<string, unknown>) {
        super(node, cellMinWidth, view as EditorView, HTMLAttributes)
        this.syncBorderless(node)
    }

    update(node: ProseMirrorNode): boolean {
        const updated = super.update(node)

        if (updated) {
            this.syncBorderless(node)
        }

        return updated
    }

    private syncBorderless(node: ProseMirrorNode) {
        this.table.toggleAttribute('data-borderless', Boolean(node.attrs.borderless))
    }
}

/**
 * Tables with drag-to-resize columns (widths kept per cell as `colwidth`) and
 * an optional borderless look for label/value blocks like "Nama : ...".
 */
export const FormTable = Table.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            borderless: {
                default: false,
                parseHTML: (element: HTMLElement) => element.hasAttribute('data-borderless'),
                renderHTML: (attributes: Record<string, unknown>) =>
                    attributes.borderless ? { 'data-borderless': 'true' } : {},
            },
        }
    },
}).configure({
    resizable: true,
    cellMinWidth: 30,
    View: FormTableView,
})

/**
 * Rows with an optional minimum height in pixels.
 */
export const FormTableRow = TableRow.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            height: {
                default: null,
                parseHTML: (element: HTMLElement) => parseInt(element.style.height, 10) || null,
                renderHTML: (attributes: Record<string, unknown>) =>
                    attributes.height ? { style: `height: ${attributes.height}px` } : {},
            },
        }
    },
})

export { TableCell as FormTableCell, TableHeader as FormTableHeader }
