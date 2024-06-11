import pandas as pd

# Define the column names as a list
columns = [
    "Title of Position",
    "University",
    "Country",
    "Supervisor",
    "Email of Supervisor",
    "LinkedIn of Supervisor",
    "More Contacts",
    "Deadline",
    "About the position"
]

# Create an empty DataFrame with the specified columns
Save = pd.DataFrame(columns=columns)

# Print the empty DataFrame
print(Save)

# Save the DataFrame to a CSV file
Save.to_csv('job_openings.csv', index=False)
