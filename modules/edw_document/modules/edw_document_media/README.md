# edw_document_media

The Document media type is going to store multiple version of a file: several formats and translations.

| Field label |       Field name        |                 Field type                 | Cardinality |    Required     |                  Translatable                  |             Widget             |                                                                                                                            Notes |
|-------------|:-----------------------:|:------------------------------------------:|:-----------:|:---------------:|:----------------------------------------------:|:------------------------------:|---------------------------------------------------------------------------------------------------------------------------------:|
| Name        |         `name`          |                    Text                    |   Single    |       Yes       |                      Yes                       |           Text field           |                                                                                                                         Built-in |
| Number      | `field_document_number` |                    Text                    |   Single    |       No        |                       No                       |                                |                                                                                                                       Text field | |
| Meeting     |    `field_meetings`     |      Node entity reference (meeting)       |  Multiple   |       No        |                       No                       |         Entity browser         |                                       A document can be related to more meetings. Entity browser to a view on meeting type ExCom |
| Country     |    `field_countries`    |   Taxonomy entity reference (countries)    |  Multiple   |       No        |                       No                       |         Chosen/Similar         |                                                                                                  Reference to Countries taxonomy |
| Type        | `field_document_types`  | Taxonomy entity reference (document_types) |  Multiple   |       Yes       |                       No                       |         Chosen/Similar         |                                                                                                      Reference to taxonomy terms |
| Files       |      `field_files`      |                    File                    |  Multiple   |       No        |                      Yes                       | Select file / Drag & drop area | Supports: DOC, DOCX, PDF, XLS, XLSX. File field allows the content manager to enter `Description` for the file (Private storage) |
| Thumbnail   |      `field_image`      |           Media entity reference           |   Single    |       No        | No (Media type should have multilingual image) |         Media library          |                                                                                                        Optional, could be hidden | |
| Date        |    `field_date_time`    |                    Date                    |   Single    | Yes (automatic) |                      Yes                       |        HTML 5 calendar         |                                                                                                                                  |