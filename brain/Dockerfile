# Use the official Microsoft Playwright image (Python version)
# This comes with all dependencies for Chromium pre-installed.
FROM mcr.microsoft.com/playwright/python:v1.42.0-jammy

# Set working directory
WORKDIR /app

# Copy requirements and install
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# We only need to install the browser binaries (libs are already there)
RUN playwright install chromium

# Copy the rest of the application
COPY . .

# Expose the port
EXPOSE 8000

# Start the application
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
