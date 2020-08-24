# Simple Import / Export tool

A tool that allows to quickly export data from Magento 1 and Magento 2 store and import it back into Magento 2. 
Table data gets exported without the knowledge of the entity identifiers and delta imports get processed by related unique keys:
- Categories: id attribute (gets stored in map table)
- Products: SKU
- Customers: email + website

When you export data from your existing stores you can configure mapping and skipped row conditions by using configuration.json:

Here is an example of configuration.json that maps all manufacturer attribute code in product data into a brand 
and skips.
```json
{
  "product_data.csv": {
    "map": {
      "attribute": {
         "manufacturer": "brand" 
      }
    },
    "skip": [
      {
        "store": ["pl", "sk"]
      }
    ]
  }
}
```

Also, you can add own mappers for exported files. Here is an example on mapping output of product attributes to create website level price instead of global during migration:

```json
{
  "product": {
    "mappers": {
      "product_attributes": [
        {
          "class": "EcomDev\\MagentoMigration\\CustomMappers\\PriceMapper",
          "setup": [
            ["withStore", "us_en", 1.0],
            ["withStore", "uk_en", 0.76],
            ["withStore", "eu_en", 0.89]
          ]
        }
      ]
    }
  }
}
```  


## Tests

Right now the automation suite might not run, as tool is released by stripping of all customer specific data from tests and codebase.
PRs to re-introduce test cases that has been removed are welcome.