import pandas as pd

# Load the CSV files
df1 = pd.read_csv('site1.csv')
df2 = pd.read_csv('site2.csv')
df3 = pd.read_csv('site3.csv')

# Concatenate the DataFrames
df_combined = pd.concat([df1, df2, df3], ignore_index=True)

# Save the combined DataFrame to a new CSV file
df_combined.to_csv('combined_sites.csv', index=False)

print("Combined CSV file has been created successfully.")
