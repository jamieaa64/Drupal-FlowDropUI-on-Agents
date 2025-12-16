# Getting Started

Follow these simple steps to create and edit your first FlowDrop workflow.

## Enable the module

   - Go to Extend (`/admin/modules`) and make sure the **FlowDrop Workflow** module is enabled.

   ![Modules page (FlowDrop section)](../../screenshots/01-modules-page-flowdrop-section.png)

   ![FlowDrop modules enabled](../../screenshots/02-flowdrop-modules-section.png)

## Create a workflow

   - Go to `/admin/structure/flowdrop-workflow` and click **+Add flowdrop workflow**.
   - Enter a **Label** and optional **Description**, then click **Save**.

   ![Workflow listing](../../screenshots/03-workflow-listing.png)

   ![Add workflow form](../../screenshots/04-add-workflow-form.png)

   ![Workflow created](../../screenshots/05-workflow-created-status.png)

## Open the workflow in the editor

   - From the workflow listing, open **List additional actions** and click **Open in Editor**.

   ![Open in Editor action](../../screenshots/06-open-in-editor-dropdown.png)

## Build and save

   - In the editor, drag components onto the canvas to build your workflow, then click **Save**.
   - **Don't forget to click on "+" button on left side to see available nodes to drag and drop.**

   ![Workflow editor](../../screenshots/07-workflow-editor.png)


## What's Next?

- Manage "FlowDrop node" by visiting "Structure >> FlowDrop Node Type" `/admin/structure/flowdrop-node-type`.
- There is a 1:M relationship between FlowDrop Node Processor (defined in code) and "Flowdrop Node Type"
  This way, you can define one processor in code and have multiple Node type with different config. A very
  common business need.

