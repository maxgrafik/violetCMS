---
title: Markdown
template: default
robots: index, follow
published: true
visible: true
---
{{Date|today@en}}

# Markdown Test {#header}

## Bold, italic, strikethrough, inline code
Et eveniet **autem ut quasi tempora perferendis** aut. In quos dolore *exercitationem eligendi*. Eaque voluptas sit ~~qui accusamus~~ expedita. Illo `sit omnis qui eos voluptates` dignissimos et velit.

## Blockquote
> Sapiente fugit consequatur in. Est eaque placeat a rerum. Consequuntur itaque ut facere nesciunt.
> Ut consequatur laudantium aut. Rerum consequatur tenetur doloremque ipsum maxime qui.

## Indented code block
	Rerum consequatur tenetur doloremque
    Est eaque placeat a rerum

## Fenced code block
```
Rerum consequatur tenetur doloremque
Est eaque placeat a rerum
```

## Unordered list
* Dolorem est est nihil ipsum et commodi tenetur.
* Aspernatur voluptas non inventore libero.
* Sed distinctio consectetur veniam. 

## Ordered list
1. Dolorem est est nihil ipsum et commodi tenetur.
2. Aspernatur voluptas non inventore libero.
3. Sed distinctio consectetur veniam. 

## Mixed nested lists
* Dolorem est est nihil ipsum et commodi tenetur.
* Aspernatur voluptas non inventore libero.
    1. In quos dolore
    2. Rerum consequatur tenetur
* Sed distinctio consectetur veniam. 

## Tables
Align left      | Align center | Align right
:----------- | :-----------: | -----------:
Row 1 Cell 1 | Row 1 Cell 2  | Row 1 Cell3
Row 2 Cell 1 | Row 2 Cell 2 | Row 2 Cell3

## Links
[Internal Link](/welcome)
[External Link](https://ddg.gg)
[Image Link](/media/editor.jpg)

## Image
![Image](/media/editor.jpg)

---

# Markdown Extra Test

## Abbreviations and Footnotes
This paragraph contains an abbrevitation (HTML)[^1] and footnotes[^2].

## Attributes {lang=en}
Assign {#id} or {.class} attributes to headlines or links. You can create links to different parts of the same document like [this](#header):

    [Link back to header](#header)

## Special Attributes
Defining an attribute `{target=_blank}` will open links in a new window. Although this practice is [debatable](https://css-tricks.com/use-target_blank/){target=\_blank rel=nofollow}

{# Abbreviation definition, ... btw this is a comment #}
*[HTML]: Hypertext Markup Language

[^1]: The definition is specified in the page source
[^2]: Another footnote.
~~~section-marker~~~
