/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { Dropdown, TextControl } from '@wordpress/components';
import * as Woo from '@woocommerce/components';
import { Fragment, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './index.scss';

const MyExamplePage = () => {
	// State to hold the value of the text input
	const [ freeText, setFreeText ] = useState( '' );

	return (
		<Fragment>
			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Free Text Field', 'Aramex-Plugin' ) }
				/>
				<TextControl
					label={ __( 'Your Input', 'Aramex-Plugin' ) }
					placeholder={ __( 'Type something...', 'Aramex-Plugin' ) }
					value={ freeText } // Bind the state to the value
					onChange={ ( value ) => setFreeText( value ) } // Update state on change
					help={ __( 'Enter any text here.', 'Aramex-Plugin' ) }
				/>
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Search', 'Aramex-Plugin' ) }
				/>
				<Woo.Search
					type="products"
					placeholder="Search for something"
					selected={ [] }
					onChange={ ( items ) => setInlineSelect( items ) }
					inlineTags
				/>
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Dropdown', 'Aramex-Plugin' ) }
				/>
				<Dropdown
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Woo.DropdownButton
							onClick={ onToggle }
							isOpen={ isOpen }
							labels={ [ 'Dropdown' ] }
						/>
					) }
					renderContent={ () => <p>Dropdown content here</p> }
				/>
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Pill shaped container', 'Aramex-Plugin' ) }
				/>
				<Woo.Pill className={ 'pill' }>
					{ __( 'Pill Shape Container', 'Aramex-Plugin' ) }
				</Woo.Pill>
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Spinner', 'Aramex-Plugin' ) }
				/>
				<Woo.H>I am a spinner!</Woo.H>
				<Woo.Spinner />
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Datepicker', 'Aramex-Plugin' ) }
				/>
				<Woo.DatePicker
					text={ __( 'I am a datepicker!', 'Aramex-Plugin' ) }
					dateFormat={ 'MM/DD/YYYY' }
				/>
			</Woo.Section>
		</Fragment>
	);
};

addFilter(
	'woocommerce_admin_pages_list',
	'Aramex-Plugin',
	( pages ) => {
		pages.push( {
			container: MyExamplePage,
			path: '/Aramex-Plugin',
			breadcrumbs: [
				__( 'Aramex Shipping AUNZ', 'Aramex-Plugin' ),
			],
			navArgs: {
				id: 'aramex_shipping_aunz',
			},
		} );

		return pages;
	}
);