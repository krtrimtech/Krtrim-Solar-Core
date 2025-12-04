# How to Release a New Version

This project uses **GitHub Actions** to automatically build and release the plugin.

## Steps to Release

1.  **Update Version Number**:
    *   Open `unified-solar-dashboard.php` and update the `Version: X.X.X` header.
    *   Open `README.md` and update the version badge/text if present.
    *   Commit these changes:
        ```bash
        git add .
        git commit -m "Bump version to 1.0.1"
        git push origin main
        ```

2.  **Create a Tag**:
    *   Create a new git tag for the version (must start with `v`):
        ```bash
        git tag v1.0.1
        ```

3.  **Push the Tag**:
    *   Push the tag to GitHub to trigger the release workflow:
        ```bash
        git push origin v1.0.1
        ```

## What Happens Next?

1.  GitHub Actions will detect the new tag `v1.0.1`.
2.  It will automatically zip the plugin files into `Krtrim-Solar-Core.zip`.
3.  It will create a new **Release** on GitHub.
4.  It will upload the zip file to that release.

## Verifying

Go to your GitHub repository's **Releases** section to see the new release and download the zip file.
