import { useEditor, type Editor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import Image from '@tiptap/extension-image'
import { BackgroundColor, Color, FontFamily, FontSize, TextStyle } from '@tiptap/extension-text-style'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import { watch, type Ref } from 'vue'
import { FormTable, FormTableCell, FormTableHeader, FormTableRow, TabNode } from './EditorExtensions'
import { VariableNode } from './VariableNode'

/**
 * One Tiptap editor configured for form templates. The extension list is the
 * contract with FormHtmlSanitizer: anything an extension here emits must be
 * kept by the sanitizer (and anything it drops should not be offered here).
 */
export function useFormEditor(options: {
    model: Ref<string>
    disabled: Ref<boolean>
    onFocus: (editor: Editor) => void
    // Every change, selection moves included -- drives the shared toolbar.
    onTransaction?: () => void
}) {
    // The HTML this editor last reported. Comparing against it (instead of
    // calling getHTML() again) keeps each keystroke to one serialisation.
    let lastEmitted = options.model.value

    const editor = useEditor({
        content: options.model.value,
        editable: !options.disabled.value,
        extensions: [
            // Links and code are left out: the sanitizer drops them, so offering
            // them would only lose the admin's formatting on save.
            StarterKit.configure({
                link: false,
                code: false,
                codeBlock: false,
                heading: { levels: [1, 2, 3, 4] },
            }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            FormTable,
            FormTableRow,
            FormTableCell,
            FormTableHeader,
            TabNode,
            VariableNode,
            TextStyle,
            FontFamily,
            FontSize,
            Color,
            BackgroundColor,
            Subscript,
            Superscript,
            // Inline so an image can share a line with text and follow its
            // alignment. Width/height are the only sizing the sanitizer keeps.
            Image.configure({
                inline: true,
                resize: {
                    enabled: true,
                    directions: ['bottom-right', 'bottom-left'],
                    minWidth: 24,
                    minHeight: 12,
                    alwaysPreserveAspectRatio: true,
                },
            }),
        ],
        editorProps: {
            attributes: { class: 'form-content' },
        },
        onUpdate: ({ editor: instance }) => {
            lastEmitted = instance.getHTML()
            options.model.value = lastEmitted
        },
        onFocus: ({ editor: instance }) => options.onFocus(instance as Editor),
        onTransaction: () => options.onTransaction?.(),
    })

    // Only push outside changes in (a sample, a copy from the other language);
    // echoing our own value back would reset the cursor on every keystroke.
    watch(options.model, (value) => {
        if (editor.value && value !== lastEmitted) {
            lastEmitted = value
            editor.value.commands.setContent(value, { emitUpdate: false })
        }
    })

    watch(options.disabled, (disabled) => editor.value?.setEditable(!disabled))

    // No destroy() here: useEditor() destroys the editor on unmount itself.

    return editor
}
