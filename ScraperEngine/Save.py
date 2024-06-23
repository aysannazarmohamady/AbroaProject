import pandas as pd

# Load the CSV files
df1 = pd.read_csv('website1.csv')
df2 = pd.read_csv('website2.csv')
df3 = pd.read_csv('website3.csv')

# Concatenate the DataFrames
df_combined = pd.concat([df1, df2, df3], ignore_index=True)

# Save the combined DataFrame to a new CSV file
df_combined.to_csv('combinedd_sites.csv', index=False)

print("Combined CSV file has been created successfully.")
