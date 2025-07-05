---
title: KeyBase organisational model
description: Describes the KeyBase organisational model
extends: _layouts.documentation
section: content
---

# KeyBase organisational model

Resources in KeyBase are scoped at one of three organisational levels:
**KeyBase** (global), **Project**, or **Key**.

-   The most important organisational level in KeyBase is the **Project**.
    **Keys**, **Items**, **Sources** and **Filters** are all at the **Project**
    level and are not shared between Projects.

-   **Leads** and **Change Notes** belong to a **Key**. Unlike resources at the
    **Project** level, like **Keys** and **Items**, which make sense outside of
    a **Project**'s context, **Leads** and **Change Notes** can only be
    understood within the context of a **Key**.

-   **Users** and **Agents** have global scope. They are both handled in the
    user profile. **Agents** in KeyBase are very tightly linked to **Users**.
    They are only used as object of the `createdBy` and `updatedBy` properties
    on all KeyBase resources and not for, _e.g._, authors of Keys and Sources
    (for which we use literals). The reason KeyBase has **Agents** is so that,
    if a **User** account is deleted, all the resources that are created or
    modified by that **User** can stay, as they are linked to the **Agent**, not
    the **User**.
   
&emsp;![organisational model](/assets/images/keybase/keybase-organisational-model.drawio.svg)
