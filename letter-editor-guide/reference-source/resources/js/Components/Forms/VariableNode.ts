import { Node, mergeAttributes } from '@tiptap/core'

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        formVariable: {
            insertFormVariable: (key: string) => ReturnType
        }
    }
}

/**
 * An inline, atomic {{key}} placeholder. It is stored as
 * `<span data-variable="key">{{key}}</span>` so the server-side renderer
 * (FormVariableService) can replace the {{key}} text with the
 * signer's value, and so the span survives the HTML sanitizer, which keeps
 * only `data-variable` on spans. Being atomic, it is deleted as one unit and
 * can never be half-edited into an unknown key.
 */
export const VariableNode = Node.create({
    name: 'formVariable',

    group: 'inline',

    inline: true,

    atom: true,

    selectable: true,

    addAttributes() {
        return {
            key: {
                default: null,
                parseHTML: element => element.getAttribute('data-variable'),
                renderHTML: attributes => ({ 'data-variable': attributes.key }),
            },
        }
    },

    parseHTML() {
        return [{ tag: 'span[data-variable]' }]
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'span',
            mergeAttributes(HTMLAttributes, { class: 'form-variable' }),
            `{{${node.attrs.key}}}`,
        ]
    },

    renderText({ node }) {
        return `{{${node.attrs.key}}}`
    },

    addCommands() {
        return {
            insertFormVariable:
                (key: string) =>
                ({ commands }) =>
                    commands.insertContent({
                        type: this.name,
                        attrs: { key },
                    }),
        }
    },
})
