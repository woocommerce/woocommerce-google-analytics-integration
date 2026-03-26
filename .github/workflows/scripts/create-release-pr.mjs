export default async ( { context, github, version } ) => {
	const refName = 'release/' + version;
	const extensionPackageName = context.payload.repository.name;
	const repoURL =
		context.payload.repository.html_url + '/tree/release/' + version;

	const title = `Release ${ version }`;

	const body = `## Checklist
1. [ ] Check if the version, base, and target branches are as you desire.
1. [ ] Make sure you have \`woorelease\` installed and set up.
1. [ ] Go to your local repo clone, and check out this PR to be able to commit any potential adjustments.
   \`\`\`sh
   git fetch origin ${ refName }
   git checkout ${ refName }
   \`\`\`
1. [ ] Remove older changelog entries from \`readme.txt\` (keep the last two versions, since we will be adding a third during the release), commit changes.
1. [ ] Update version and generate changelog. You will be asked to review the changelog and as result this will add a couple of commits to this PR
   \`\`\`sh
   woorelease vb:change --release --generate_changelog --product_version=${ version } ${ repoURL }
   woorelease vb:replace --release --product_version=${ version } ${ repoURL }
   woorelease cl:generate --release --product_version=${ version } ${ repoURL }
   \`\`\`
1. [ ] Automated tests are passing.
1. [ ] Run [Woo Deploy Action](${ context.payload.repository.html_url }/actions/workflows/deploy.yml) — select branch \`release/${ version }\`, version \`${ version }\`, and **Dry run** mode.
1. [ ] Test the package
   1. [ ] Install the .zip package from the artifact from the dry run on a test site
   1. [ ] Confirm it activates without warnings/errors and is showing the right versions
   1. [ ] Run a few basic smoke tests

## Next steps
1. [ ] Do the final release
   1. [ ] Run [Woo Deploy Action](${ context.payload.repository.html_url }/actions/workflows/deploy.yml) — select branch \`release/${ version }\`, version \`${ version }\`, and **production** mode (disable **Dry run**).
1. [ ] Confirm the release using the activation link from your email.
   When releasing to WordPress.org, _"release notifications"_ have been enabled, so each committer will be sent an email with an action link to confirm the release. This must be done after committing in SVN before the release becomes available. See the following page for releases pending notifications: https://wordpress.org/plugins/developers/releases/
1. [ ] Go to ${ context.payload.repository.html_url }/releases/${ version }, generate GitHub release notes, and paste them as a comment here.
1. [ ] Merge this PR after the new release is successfully created and the version tags are updated.
1. [ ] Merge \`trunk\` to \`develop\` (PR), if applicable for this repo.
1. [ ] Update documentation
   - [ ] Publish any new required docs
   - [ ] Update triggers/rules/actions listing pages
1. [ ] Mark related ideas complete [on the feature requests page](https://woocommerce.com/feature-requests/${ extensionPackageName }/).
`;

	const pull = await github.rest.pulls.create( {
		...context.repo,
		base: 'trunk',
		head: refName,
		title,
		body,
	} );
	return pull.data;
};
