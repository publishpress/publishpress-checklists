---
title: "OpenAI Prompts for Tasks"
order: 38
doc_category: checklists
---

# OpenAI Prompts for Tasks

The [PublishPress Checklists](https://publishpress.com/links/checklists) plugin integrates with OpenAI. You can use prompts to analyze your content. If OpenAI decides that your content doesn’t meet the requirements, it will show the task as incomplete.

To use this feature, go to Checklists > Settings > General and enter your OpenAI API Key:

![](https://docs-media.publishpress.com/publishpress-checklists/open-api-key-5a17442e23.webp)

To demonstrate how the OpenAI integration works, we’ve provided several sample prompts. You can create checklist requirements like these:

- “Is the content clear and easy to read?”
- “Is this content tone professional?”
- “Does this article use correct grammar?”

![OpenAI checklist requirement](https://docs-media.publishpress.com/publishpress-checklists/openai-checklist-item-e1ba8709d8.webp)

As an example, this screenshot below uses the text from the [About WordPress](https://wordpress.org/about/) article. The checklist requirement was “Is the content clear and easy to read?” OpenAI has scanned the content and given a detailed verdict. The content is approved as clear and easy to read.

![Sample OpenAI article and PublishPress Checklists](https://docs-media.publishpress.com/publishpress-checklists/clear-content-checklist-3fee7ff063.webp)

In this next screenshot, I’ve used the same requirement to analyze a passage by the author James Joyce. The passage may have many literary merits, but OpenAI confirms that the passage is neither clear nor easy to read.

![James Joyce and PublishPress Checklists](https://docs-media.publishpress.com/publishpress-checklists/james-joyce-sample-4e51b3224e.webp)

In this next example, I’ve changed the requirement. This time the prompt is “Does this article use correct grammar?”

![Grammar checklist requirement](https://docs-media.publishpress.com/publishpress-checklists/good-grammar-115e48ca87.webp)

To test this prompt, I took [a BBC article](https://www.bbc.com/travel/article/20240109-10-destinations-welcoming-sustainable-travellers-in-2024) and added some spelling and grammar mistakes. OpenAI analyzed the article, and as you can see in the screenshot below, it provided details of all the mistakes.

![OpenAI and grammar errors in WordPress](https://docs-media.publishpress.com/publishpress-checklists/grammar-fail-52890f6f3b.webp)

OpenAI is smart enough to recognize the difference between deliberate spelling variations and accidental mistakes. In this next example, I’ve used an article about the Appalachian English dialect. OpenAI responds that the grammar used in the article is specific to a regional variation.

![Appalachian English and OpenAI](https://docs-media.publishpress.com/publishpress-checklists/appalachian-english-9b9689e5f7.webp)
